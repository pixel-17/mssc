<?php

namespace App\Actions\Usuario;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Alta de un usuario nuevo dentro de la pirámide (ver UserPolicy):
 *
 * - Jefe Inmediato crea un Trabajador y se asigna a sí mismo como jefe
 *   inmediato ADICIONAL (tabla jefes_inmediatos_adicionales), sin
 *   importar la unidad orgánica del trabajador.
 * - Jefe de Área crea un Trabajador (con unidad_organica_id dentro de
 *   su propia área) o un Jefe Inmediato (mismo caso, pero además pone
 *   al nuevo usuario como jefe_id de esa unidad — eso es justamente lo
 *   que lo convierte en "Jefe Inmediato", ver UnidadOrganica::jefeInmediato()).
 *
 * jefe_inmediato_id/jefe_area_id del nuevo usuario se recalculan solos
 * vía UserObserver en cuanto se guarda con unidad_organica_id (si la
 * tiene). Nunca se tocan a mano aquí.
 *
 * Herencia cuando el creador es Jefe Inmediato (! $esJefeDeArea):
 * sede_id y unidad_organica_id del trabajador nuevo se heredan SIEMPRE
 * del creador (no son editables desde el formulario, aunque lleguen en
 * $datos) — así unidad_organica_id queda igual a la del Jefe Inmediato
 * y UserObserver calcula jefe_inmediato_id = el propio creador. Cuando
 * el creador es Jefe de Área, unidad_organica_id sí viene del
 * formulario (una de las unidades de su subárbol) y sede_id se deja
 * como lo eligió el formulario, porque una misma área puede abarcar
 * más de una sede.
 *
 * Contraseña inicial: siempre el DNI del propio usuario (nunca la
 * elige quien lo crea). debe_actualizar_password queda en true para
 * que RedirigirSiDebeActualizarPassword le pida cambiarla —de forma
 * opcional, puede omitirlo— la primera vez que entre.
 *
 * admin NO pasa por aquí: usa UsuarioAdminForm (mismo criterio de
 * contraseña = DNI, ver ese componente).
 */
class CrearUsuarioAction
{
    public function __construct(
        private GeneradorTurnoMensualService $generadorTurno,
    ) {}

    /**
     * @param  array{name:string,apellido:string,dni:string,email:string,regimen:string,sede_id:?int,unidad_organica_id:?int,tipo:string,turno:?string,fecha_ancla:?string,dias_trabajo:?int,dias_descanso:?int}  $datos
     */
    public function ejecutar(User $creador, array $datos, bool $esJefeDeArea): User
    {
        return DB::transaction(function () use ($creador, $datos, $esJefeDeArea) {
            // Reingreso: si el DNI corresponde a alguien ya desactivado
            // (ver UsuarioAdminIndex::desactivar(), que desactiva en vez de
            // borrar), reactivamos esa misma fila en vez de crear una
            // nueva — conserva su historial de papeletas bajo el mismo
            // id. CrearUsuarioRequest ya garantiza que si existe un
            // usuario ACTIVO con ese DNI, ni siquiera llegamos aquí.
            $existente = User::where('dni', $datos['dni'])->lockForUpdate()->first();

            if ($existente) {
                $this->validarReingreso($existente);
            }

            $atributos = [
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'dni' => $datos['dni'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['dni']),
                'debe_actualizar_password' => true,
                'activo' => true,
                'regimen' => $datos['regimen'],
                'sede_id' => $esJefeDeArea ? ($datos['sede_id'] ?? null) : $creador->sede_id,
                'unidad_organica_id' => $esJefeDeArea ? $datos['unidad_organica_id'] : $creador->unidad_organica_id,
            ];

            if ($existente) {
                // Reingreso: la persona vuelve con contraseña = DNI y, posiblemente,
                // otro correo; el segundo factor de la cuenta anterior ya no
                // corresponde a nadie y se descarta.
                $existente->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ])->fill($atributos)->save();

                $nuevo = $existente;
            } else {
                $nuevo = User::create($atributos);
            }

            if (! $nuevo->hasRole('trabajador')) {
                $nuevo->assignRole('trabajador');
            }

            if ($esJefeDeArea && $datos['tipo'] === 'jefe_inmediato') {
                $this->asignarComoJefeDeUnidad($nuevo, (int) $datos['unidad_organica_id']);
            }

            if (! $esJefeDeArea) {
                // Jefe Inmediato creando (o reingresando a) un trabajador
                // propio: se asigna a sí mismo directo. syncWithoutDetaching
                // en vez de attach() para no romper si la relación ya
                // existía de una asignación anterior al reingreso.
                $nuevo->jefesInmediatosAdicionales()->syncWithoutDetaching([
                    $creador->id => ['asignado_por_id' => $creador->id],
                ]);
            }

            // 728 SIEMPRE necesita su turno vigente para crear una
            // papeleta (ver CrearPapeletaAction): CrearUsuarioRequest ya
            // exige turno/fecha_ancla cuando regimen es 728, así que acá
            // solo se carga. Reingreso incluido: si vuelve como 728,
            // también necesita su ciclo desde el primer día.
            if ($datos['regimen'] === '728') {
                $this->generadorTurno->cargarConfiguracion(
                    trabajador: $nuevo,
                    turno: $datos['turno'],
                    fechaAncla: Carbon::parse($datos['fecha_ancla']),
                    actor: $creador,
                    diasTrabajo: (int) ($datos['dias_trabajo'] ?? 6),
                    diasDescanso: (int) ($datos['dias_descanso'] ?? 1),
                );
            }

            return $nuevo->fresh();
        });
    }

    /**
     * Un reingreso REEMPLAZA correo y contraseña (= DNI, que no es un
     * secreto) de una cuenta existente. Por eso solo se acepta cuando es
     * inocuo: la cuenta está desactivada y no tiene más permisos que
     * "trabajador". Sin esto, un Jefe podía "dar de alta" el DNI de un
     * ex-administrador o ex-RR. HH., poner su propio correo y entrar con
     * esos roles intactos. Esas cuentas las reactiva solo el admin, desde
     * UsuarioAdminIndex::reactivar().
     *
     * También es una defensa por si esta Action se llama sin pasar por
     * CrearUsuarioRequest: nunca pisa a un usuario activo.
     */
    private function validarReingreso(User $existente): void
    {
        if ($existente->activo) {
            throw new UsuarioException('Ya existe un usuario activo con ese DNI.');
        }

        $rolesEspeciales = $existente->getRoleNames()->diff(['trabajador']);

        if ($rolesEspeciales->isNotEmpty()) {
            throw new UsuarioException(
                'Ese DNI corresponde a una cuenta desactivada con permisos especiales (administración o RR. HH.). '.
                'Solo un administrador puede reactivarla desde Usuarios.'
            );
        }
    }

    private function asignarComoJefeDeUnidad(User $nuevo, int $unidadId): void
    {
        $unidad = UnidadOrganica::findOrFail($unidadId);

        if ($unidad->jefe_id) {
            throw new UsuarioException(
                "La unidad \"{$unidad->nombre}\" ya tiene un jefe asignado. Reasígnala desde el organigrama antes de crear otro."
            );
        }

        $unidad->update(['jefe_id' => $nuevo->id]);
    }
}
