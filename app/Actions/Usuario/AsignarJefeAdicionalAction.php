<?php

namespace App\Actions\Usuario;

use App\Exceptions\PapeletaException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * jefes_inmediatos_adicionales (2026_09_10_200000): asigna a mano un
 * jefe inmediato EXTRA a un trabajador, aparte del automático derivado
 * de la unidad orgánica (users.jefe_inmediato_id, que esta acción
 * nunca toca).
 *
 * Quién puede asignar (ver comentario de la migración): un admin, el
 * jefe de área del trabajador, o cualquier otro jefe inmediato que el
 * trabajador ya tenga (automático o adicional) — de ahí que se
 * autorice con User::esJefeInmediatoDe() en vez de un rol fijo.
 *
 * "La UI debe mostrar los jefes que ya tiene ... y pedir confirmación
 * explícita antes de insertar uno nuevo": por eso $confirmado es
 * obligatorio y no tiene default, para que no sea posible llamar esta
 * acción sin que quien la invoca haya pasado por ese paso.
 */
class AsignarJefeAdicionalAction
{
    public function ejecutar(
        User $trabajador,
        User $jefeNuevo,
        User $asignadoPor,
        bool $confirmado,
    ): User {
        if ($trabajador->id === $jefeNuevo->id) {
            throw new PapeletaException('Un trabajador no puede ser su propio jefe inmediato.');
        }

        if (! $confirmado) {
            throw new PapeletaException('Falta confirmar explícitamente la asignación de jefe adicional.');
        }

        $puedeAsignar = $asignadoPor->hasRole('admin')
            || $asignadoPor->id === $trabajador->jefe_area_id
            || $asignadoPor->esJefeInmediatoDe($trabajador);

        if (! $puedeAsignar) {
            throw new PapeletaException('No tienes permiso para asignar jefes inmediatos a este trabajador.');
        }

        if ($trabajador->jefe_inmediato_id === $jefeNuevo->id) {
            throw new PapeletaException('Ese usuario ya es el jefe inmediato automático de este trabajador.');
        }

        if ($trabajador->jefesInmediatosAdicionales()->where('users.id', $jefeNuevo->id)->exists()) {
            throw new PapeletaException('Ese usuario ya es jefe inmediato adicional de este trabajador.');
        }

        DB::transaction(function () use ($trabajador, $jefeNuevo, $asignadoPor) {
            $trabajador->jefesInmediatosAdicionales()->attach($jefeNuevo->id, [
                'asignado_por_id' => $asignadoPor->id,
            ]);
        });

        return $trabajador->fresh();
    }
}
