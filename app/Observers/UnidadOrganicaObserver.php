<?php

namespace App\Observers;

use App\Models\UnidadOrganica;

/**
 * Cuando cambia el jefe de una unidad (jefe_id) o su padre (parent_id),
 * el jefe_inmediato_id/jefe_area_id de TODOS los usuarios afectados deja
 * de coincidir con el árbol. Este observer recalcula en cascada:
 *
 * - Cambio de jefe_id  -> afecta el jefe_inmediato_id de los miembros
 *   directos de esta unidad, y el jefe_area_id de los miembros de cada
 *   unidad HIJA (porque su jefe de área es el jefe de ESTA unidad).
 * - Cambio de parent_id -> afecta el jefe_area_id de los miembros
 *   directos de esta unidad (su jefe de área pasa a ser el jefe del
 *   nuevo padre).
 *
 * Importante: esto NO toca papeletas ya creadas (esas ya fotografiaron
 * su propio jefe_inmediato_id/jefe_area_id al momento de crearse, y son
 * inmutables por diseño). Solo mantiene al día el dato "vigente" en
 * `users`, que es lo que se fotografía en la SIGUIENTE papeleta.
 */
class UnidadOrganicaObserver
{
    public function saved(UnidadOrganica $unidad): void
    {
        if ($unidad->wasChanged('jefe_id')) {
            $this->resincronizarMiembrosDirectos($unidad);
            $this->resincronizarMiembrosDeHijos($unidad);
        }

        if ($unidad->wasChanged('parent_id')) {
            $this->resincronizarMiembrosDirectos($unidad);
        }
    }

    private function resincronizarMiembrosDirectos(UnidadOrganica $unidad): void
    {
        $unidad->miembros->each(function ($usuario) {
            app(\App\Observers\UserObserver::class)->resincronizar($usuario);
            $usuario->saveQuietly();
        });
    }

    private function resincronizarMiembrosDeHijos(UnidadOrganica $unidad): void
    {
        foreach ($unidad->hijos as $hijo) {
            $this->resincronizarMiembrosDirectos($hijo);
        }
    }
}
