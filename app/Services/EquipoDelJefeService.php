<?php

namespace App\Services;

use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fuente única de "qué trabajadores ve este jefe" en las pantallas de
 * turnos (calendario de equipo y programación de equipo). A propósito
 * usa un alcance MÁS ANGOSTO que User::equipoDe() (que sí usan
 * papeletas/reportes/dashboard, donde el Jefe de Área debe ver a todo
 * su personal en cadena de aprobación/histórico):
 *
 * - Jefe Inmediato: sus trabajadores directos (automáticos o
 *   adicionales), los de su unidad que trabajan el turno que cubre
 *   como jefe de turno (jefes_turno) y él mismo. Además VE (solo
 *   lectura, no los programa) a los demás jefes inmediatos de sus
 *   unidades: ver el tercer elemento de para().
 * - Jefe de Área: sus Jefes Inmediatos (los jefes de las sub-unidades
 *   de su área, de cualquier nivel) y él mismo — NO a los
 *   trabajadores de esas sub-unidades, salvo los que además tenga
 *   asignados a él de forma directa (porque, en su propia oficina,
 *   también funge como Jefe Inmediato de algunos).
 */
class EquipoDelJefeService
{
    /**
     * El tercer elemento son los ids de quienes se MUESTRAN pero no se
     * pueden programar (jefes inmediatos pares): las pantallas de
     * programación los excluyen de lo editable.
     *
     * @return array{0: Collection<int, User>, 1: bool, 2: list<int>}  [personas, esJefeDeArea, idsSoloLectura]
     */
    public function para(User $user): array
    {
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);
        $esJefeDeArea = $unidadIds->isNotEmpty();

        $paresSoloLectura = collect();

        $directos = $user->subordinadosInmediatos()->get()
            ->merge($user->trabajadoresAdicionales()->get())
            ->merge(User::deLosTurnosQueCubre($user)->get());

        // Un Jefe Inmediato (que no es Jefe de Área) solo ve y programa a
        // trabajadores, no a otros jefes inmediatos (ver
        // User::puedeGestionarTurnoDe). Se le quita de la lista a todo el
        // que sea jefe de alguien, salvo él mismo. El admin no se recorta.
        if (! $esJefeDeArea && ! $user->hasRole('admin')) {
            $idsDeJefes = $this->idsDeJefes();

            $directos = $directos->reject(
                fn (User $t) => $t->id !== $user->id && $idsDeJefes->contains($t->id)
            );
        }

        // Los demás jefes inmediatos de las unidades donde este usuario es
        // jefe (de la unidad o adicional) sí se ven, pero solo lectura:
        // entre jefes inmediatos cada uno programa solo su propio turno.
        if (! $user->hasRole('admin')) {
            $paresSoloLectura = $this->jefesDeSusUnidades($user)
                ->reject(fn (User $j) => $j->id === $user->id);
        }

        $jefesDeSubunidades = $esJefeDeArea
            ? User::whereIn('id', UnidadOrganica::whereIn('id', $unidadIds)
                ->whereNotNull('jefe_id')
                ->pluck('jefe_id')
                ->merge(JefeTurno::whereIn('unidad_organica_id', $unidadIds)->pluck('jefe_id'))
                ->unique())
                ->get()
            : collect();

        $trabajadores = $directos
            ->merge($jefesDeSubunidades)
            ->merge($paresSoloLectura)
            ->push($user)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [
            $trabajadores,
            $esJefeDeArea,
            $paresSoloLectura->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * Jefes inmediatos (jefe de la unidad + adicionales de jefes_turno)
     * de las unidades donde $user es jefe inmediato (de la unidad o adicional).
     *
     * @return Collection<int, User>
     */
    private function jefesDeSusUnidades(User $user): Collection
    {
        $unidadIds = JefeTurno::where('jefe_id', $user->id)->pluck('unidad_organica_id')
            ->merge(UnidadOrganica::where('jefe_id', $user->id)->pluck('id'))
            ->unique();

        if ($unidadIds->isEmpty()) {
            return collect();
        }

        $jefeIds = UnidadOrganica::whereIn('id', $unidadIds)
            ->whereNotNull('jefe_id')
            ->pluck('jefe_id')
            ->merge(JefeTurno::whereIn('unidad_organica_id', $unidadIds)->pluck('jefe_id'))
            ->unique();

        return User::whereIn('id', $jefeIds)->where('activo', true)->get();
    }

    /**
     * IDs de todos los usuarios que son jefe de alguien: jefe de unidad,
     * jefe de turno (jefes_turno), jefe adicional o jefe inmediato de al
     * menos un trabajador. Mismo criterio que User::esJefeDeAlguien(),
     * pero en pocas consultas para no repetirlo por cada trabajador.
     *
     * @return Collection<int, int>
     */
    public function idsDeJefes(): Collection
    {
        return UnidadOrganica::whereNotNull('jefe_id')->pluck('jefe_id')
            ->merge(JefeTurno::pluck('jefe_id'))
            ->merge(DB::table('jefes_inmediatos_adicionales')->pluck('jefe_inmediato_id'))
            ->merge(User::whereNotNull('jefe_inmediato_id')->distinct()->pluck('jefe_inmediato_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * IDs de todas las unidades (encabezadas + sub-unidades) donde el
     * usuario es Jefe de Área. Vacío si solo es Jefe Inmediato.
     */
    private function subtreeIdsDeAreasQueEncabeza(User $user): Collection
    {
        $ids = collect();

        foreach ($user->unidadesDeArea as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}