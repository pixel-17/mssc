<?php

namespace App\Observers;

use App\Models\User;

/**
 * Mantiene sincronizados jefe_inmediato_id / jefe_area_id (valores
 * explícitos que las papeletas fotografían) con la posición real del
 * usuario en el árbol de unidades_organicas.
 *
 * Estos dos campos NO se editan a mano desde ningún formulario: se
 * recalculan solos cada vez que cambia unidad_organica_id. Si en algún
 * momento se necesita reasignar jefe sin mover al trabajador de unidad,
 * eso se hace cambiando jefe_id en la UnidadOrganica (ver
 * UnidadOrganicaObserver), no aquí.
 */
class UserObserver
{
    public function saving(User $user): void
    {
        if (! $user->isDirty('unidad_organica_id')) {
            return;
        }

        $this->resincronizar($user);
    }

    public function resincronizar(User $user): void
    {
        $unidad = $user->unidad_organica_id
            ? \App\Models\UnidadOrganica::find($user->unidad_organica_id)
            : null;

        $user->jefe_inmediato_id = $unidad?->jefeInmediato()?->id;
        $user->jefe_area_id = $unidad?->jefeArea()?->id;
    }
}
