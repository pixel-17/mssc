<?php

namespace App\Observers;

use App\Models\UnidadOrganica;
use App\Models\User;

/**
 * Cuando cambia el jefe de una unidad (jefe_id) o su padre (parent_id),
 * el jefe_inmediato_id/jefe_area_id de los usuarios afectados deja de
 * coincidir con el árbol. Este observer recalcula la unidad y TODO su
 * subárbol, sin límite de niveles.
 *
 * Por qué todo el subárbol y no solo hijos directos: quien encabeza una
 * unidad tiene como jefe de área al jefe de la unidad ABUELA (ver
 * UnidadOrganica::jefaturasDe), así que un cambio de jefe se propaga
 * dos niveles hacia abajo a los jefes de esas unidades. Recorrer todo
 * el subárbol es más simple que razonar cada caso, y el organigrama
 * es chico (oficinas, no trabajadores).
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
        if (! $unidad->wasChanged(['jefe_id', 'parent_id'])) {
            return;
        }

        $this->resincronizarSubarbol($unidad);
    }

    private function resincronizarSubarbol(UnidadOrganica $unidad): void
    {
        $unidadIds = [$unidad->id, ...$unidad->descendantIds()];

        User::whereIn('unidad_organica_id', $unidadIds)->each(function (User $usuario) {
            app(UserObserver::class)->resincronizar($usuario);
            $usuario->saveQuietly();
        });
    }
}
