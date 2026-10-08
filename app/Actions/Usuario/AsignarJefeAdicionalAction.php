<?php

namespace App\Actions\Usuario;

use App\Exceptions\PapeletaException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * jefes_inmediatos_adicionales (2026_09_10_200000): asigna a mano un
 * jefe inmediato a un trabajador concreto, con las mismas capacidades
 * que cualquier otro jefe inmediato suyo (users.jefe_inmediato_id, que
 * esta acción nunca toca).
 *
 * Quién puede asignar (ver comentario de la migración): un admin, el
 * jefe de área del trabajador, o cualquier jefe inmediato que el
 * trabajador ya tenga (todos son iguales) — de ahí que se
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

        if ($jefeNuevo->hasRole('admin')) {
            throw new PapeletaException('Un administrador no puede ser jefe inmediato: no tiene bandeja de jefe.');
        }

        if (! $confirmado) {
            throw new PapeletaException('Falta confirmar explícitamente la asignación de jefe inmediato.');
        }

        $puedeAsignar = $asignadoPor->hasRole('admin')
            || $asignadoPor->id === $trabajador->jefe_area_id
            || $asignadoPor->esJefeInmediatoDe($trabajador);

        if (! $puedeAsignar) {
            throw new PapeletaException('No tienes permiso para asignar jefes inmediatos a este trabajador.');
        }

        // Da igual por dónde le venga la jefatura (su unidad, jefes_turno
        // o una asignación manual previa): todos son jefes inmediatos.
        if ($jefeNuevo->esJefeInmediatoDe($trabajador)) {
            throw new PapeletaException('Ese usuario ya es jefe inmediato de este trabajador.');
        }

        DB::transaction(function () use ($trabajador, $jefeNuevo, $asignadoPor) {
            $trabajador->jefesInmediatosAdicionales()->attach($jefeNuevo->id, [
                'asignado_por_id' => $asignadoPor->id,
            ]);
        });

        return $trabajador->fresh();
    }
}
