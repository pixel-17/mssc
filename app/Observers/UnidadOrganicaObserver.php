<?php

namespace App\Observers;

use App\Actions\Organigrama\PapeletasAbiertasService;
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
 * Las papeletas ya creadas que siguen esperando al jefe (Pendiente
 * Jefe / Observada por jefe) pasan al jefe nuevo (ver
 * PapeletasAbiertasService); las demás son historial y no se tocan.
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

        // Árbol cargado una sola vez: jefaturasDe() lee padre, padre->padre,
        // jefe, y sin esto cada usuario del subárbol los
        // consultaba por su cuenta (N+1).
        $arbol = UnidadOrganica::arbolEnMemoria();
        $observer = app(UserObserver::class);
        $reasignar = app(PapeletasAbiertasService::class);

        User::whereIn('unidad_organica_id', $unidadIds)
            ->chunkById(500, function ($usuarios) use ($arbol, $observer, $reasignar) {
                foreach ($usuarios as $usuario) {
                    $observer->resincronizar($usuario, $arbol->get($usuario->unidad_organica_id));
                    $cambioJefe = $usuario->isDirty('jefe_inmediato_id');
                    $usuario->saveQuietly();

                    // Su jefe cambió: sus papeletas abiertas pasan al jefe nuevo.
                    if ($cambioJefe) {
                        $reasignar->reasignarDeTrabajador($usuario);
                    }
                }
            });
    }
}
