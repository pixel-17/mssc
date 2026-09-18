<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Fuente única de "qué trabajadores ve este jefe" en las pantallas de
 * turnos (calendario de equipo y programación de equipo), para no
 * mantener dos criterios de alcance distintos.
 *
 * Jefe de Área: todos los usuarios de su unidad y de TODAS las
 * sub-unidades debajo (mismo criterio que UsuarioController::index).
 * Jefe Inmediato: sus trabajadores directos (automáticos o adicionales)
 * más él mismo, para que pueda gestionar su propio turno.
 */
class EquipoDelJefeService
{
    /**
     * @return array{0: Collection<int, User>, 1: bool}  [trabajadores, esJefeDeArea]
     */
    public function para(User $user): array
    {
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);
        $esJefeDeArea = $unidadIds->isNotEmpty();

        $trabajadores = $esJefeDeArea
            ? User::whereIn('unidad_organica_id', $unidadIds)->orderBy('name')->get()
            : $user->trabajadoresComoJefeInmediato()
                ->push($user)
                ->unique('id')
                ->sortBy('name')
                ->values();

        return [$trabajadores, $esJefeDeArea];
    }

    /**
     * IDs de todas las unidades (encabezadas + sub-unidades) donde el
     * usuario es Jefe de Área. Vacío si solo es Jefe Inmediato.
     */
    private function subtreeIdsDeAreasQueEncabeza(User $user): Collection
    {
        $ids = collect();

        foreach ($user->unidadesQueEncabeza as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
