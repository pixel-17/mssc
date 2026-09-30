<?php

namespace App\Actions\Usuario;

use App\Exceptions\UsuarioException;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\AlertaJefaturaService;
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
 *   su propia área) o un Jefe Inmediato. El primer jefe_inmediato de
 *   una unidad queda como jefe_id (titular); en régimen 728 puede
 *   haber hasta 3 en total (jefe_id + hasta 2 más en jefes_turno, ver
 *   asignarComoJefeDeUnidad()) — uno no puede coincidir con el turno
 *   YA cubierto (según su propia configuración de calendario) por otro
 *   jefe activo de la misma unidad. En 276 solo se admite uno.
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
 *
 * Si el trabajador 728 recién cargado queda en un turno sin jefe
 * inmediato activo, se avisa a admin + Jefe de Área (ver
 * AlertaJefaturaService) — no bloquea el alta, solo informa.
 */
class CrearUsuarioAction
{
    public function __construct(
        private GeneradorTurnoMensualService $generadorTurno,
        private AlertaJefaturaService $alertaJefatura,
    ) {}

    /**
     * @param  array{name:string,apellido:string,dni:string,email:string,regimen:string,sede_id:?int,unidad_organica_id:?int,tipo:string,turno:?string,fecha_ancla:?string,dias_trabajo:?int,dias_descanso:?int}  $datos
     */
    public function ejecutar(User $creador, array $datos, bool $esJefeDeArea): User
    {
        $esJefeInmediatoNuevo = $esJefeDeArea && ($datos['tipo'] ?? 'trabajador') === 'jefe_inmediato';

        $nuevo = DB::transaction(function () use ($creador, $datos, $esJefeDeArea, $esJefeInmediatoNuevo) {
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
                'sede_id' => $this->resolverSede($creador, $datos, $esJefeDeArea, $esJefeInmediatoNuevo),
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

            if ($esJefeInmediatoNuevo) {
                $this->asignarComoJefeDeUnidad($nuevo, (int) $datos['unidad_organica_id'], $datos['regimen']);
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
            // Un jefe inmediato NO se crea con turno: se programa el suyo.
            if ($datos['regimen'] === '728' && ! $esJefeInmediatoNuevo) {
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

        // Fuera de la transacción: mismo motivo que CrearPapeletaAction —
        // si el envío de la notificación falla, nunca debe revertir el
        // alta ya confirmada en BD. Solo aplica a 728 (jefes_turno no se
        // usa en 276, ver AlertaJefaturaService).
        if ($datos['regimen'] === '728' && ! $esJefeInmediatoNuevo && $nuevo->unidadOrganica) {
            $this->alertaJefatura->avisarSiFaltaJefeDeTurno($nuevo->unidadOrganica, $datos['turno']);
        }

        return $nuevo;
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

    private function asignarComoJefeDeUnidad(User $nuevo, int $unidadId, string $regimen): void
    {
        $unidad = UnidadOrganica::findOrFail($unidadId);

        if (! $unidad->jefe_id) {
            $unidad->update(['jefe_id' => $nuevo->id]);

            // 728: el titular también entra a jefes_turno como uno más —
            // resolverJefeInmediato()/resolverJefesInmediatos() para los
            // turnos rotativos SOLO miran jefes_turno (ver UnidadOrganica),
            // el turno que cubre sale de su propia configuración de
            // calendario (cargada más abajo en ejecutar()), no de aquí.
            if ($regimen === '728') {
                JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $nuevo->id]);
            }

            return;
        }

        if ($regimen !== '728') {
            throw new UsuarioException(
                "La unidad \"{$unidad->nombre}\" ya tiene su jefe inmediato. Reasígnala desde el organigrama antes de crear otro."
            );
        }

        $jefesActuales = JefeTurno::where('unidad_organica_id', $unidad->id)->pluck('jefe_id');

        if ($jefesActuales->count() >= 3) {
            throw new UsuarioException(
                "La unidad \"{$unidad->nombre}\" ya tiene sus 3 jefes inmediatos (uno por turno)."
            );
        }

        // Sin chequeo de turno ocupado: el jefe nuevo nace SIN turno y se
        // programa el suyo después; si dos jefes terminan cubriendo el
        // mismo turno, AlertaJefaturaService avisa los huecos.
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $nuevo->id]);
    }

    /**
     * Sede del usuario nuevo (nunca null para un jefe inmediato):
     * - Jefe inmediato creado por Jefe de Área: la elige el formulario
     *   (puede ser otra sede); si por algún motivo no llega, cae a la
     *   del creador en vez de quedar vacía.
     * - Trabajador: por defecto la sede de su jefe inmediato (jefe_id de
     *   la unidad) o, si no hay, la del creador. No se elige a mano.
     */
    private function resolverSede(User $creador, array $datos, bool $esJefeDeArea, bool $esJefeInmediatoNuevo): ?int
    {
        if ($esJefeInmediatoNuevo) {
            return $datos['sede_id'] ?? $creador->sede_id;
        }

        if (! $esJefeDeArea) {
            return $creador->sede_id;
        }

        $sedeDelJefe = UnidadOrganica::with('jefe')->find($datos['unidad_organica_id'])?->jefe?->sede_id;

        return $sedeDelJefe ?? $creador->sede_id;
    }
}
