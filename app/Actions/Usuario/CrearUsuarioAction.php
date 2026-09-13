<?php

namespace App\Actions\Usuario;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
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
    /**
     * @param  array{name:string,apellido:string,dni:string,email:string,regimen:string,sede_id:?int,unidad_organica_id:?int,tipo:string}  $datos
     */
    public function ejecutar(User $creador, array $datos, bool $esJefeDeArea): User
    {
        return DB::transaction(function () use ($creador, $datos, $esJefeDeArea) {
            $nuevo = User::create([
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'dni' => $datos['dni'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['dni']),
                'debe_actualizar_password' => true,
                'regimen' => $datos['regimen'],
                'sede_id' => $datos['sede_id'] ?? null,
                'unidad_organica_id' => $esJefeDeArea ? $datos['unidad_organica_id'] : null,
            ]);

            $nuevo->assignRole('trabajador');

            if ($esJefeDeArea && $datos['tipo'] === 'jefe_inmediato') {
                $this->asignarComoJefeDeUnidad($nuevo, (int) $datos['unidad_organica_id']);
            }

            if (! $esJefeDeArea) {
                // Jefe Inmediato creando trabajador propio: se asigna a sí
                // mismo directo, sin pasar por el flujo de confirmación
                // (no puede "ya tener jefe" porque recién se crea).
                $nuevo->jefesInmediatosAdicionales()->attach($creador->id, [
                    'asignado_por_id' => $creador->id,
                ]);
            }

            return $nuevo->fresh();
        });
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
