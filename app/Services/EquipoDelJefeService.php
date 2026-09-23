<?php

namespace App\Services;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Fuente única de "qué trabajadores ve este jefe" en las pantallas de
 * turnos (calendario de equipo y programación de equipo). A propósito
 * usa un alcance MÁS ANGOSTO que User::equipoDe() (que sí usan
 * papeletas/reportes/dashboard, donde el Jefe de Área debe ver a todo
 * su personal en cadena de aprobación/histórico):
 *
 * - Jefe Inmediato: sus trabajadores directos (automáticos o
 *   adicionales) y él mismo.
 * - Jefe de Área: sus Jefes Inmediatos (los jefes de las sub-unidades
 *   de su área, de cualquier nivel) y él mismo — NO a los
 *   trabajadores de esas sub-unidades, salvo los que además tenga
 *   asignados a él de forma directa (porque, en su propia oficina,
 *   también funge como Jefe Inmediato de algunos).
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

        $directos = $user->subordinadosInmediatos()->get()
            ->merge($user->trabajadoresAdicionales()->get());

        $jefesDeSubunidades = $esJefeDeArea
            ? User::whereIn('id', UnidadOrganica::whereIn('id', $unidadIds)
                ->whereNotNull('jefe_id')
                ->pluck('jefe_id'))
                ->get()
            : collect();

        $trabajadores = $directos
            ->merge($jefesDeSubunidades)
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
