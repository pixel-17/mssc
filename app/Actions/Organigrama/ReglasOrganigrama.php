<?php

namespace App\Actions\Organigrama;

use App\Models\UnidadOrganica;
use App\Models\User;

/**
 * Reglas del organigrama que antes estaban repetidas en las Actions de
 * mover y en UserPolicy. Un solo lugar para que el árbol, los formularios
 * y las políticas no puedan contradecirse:
 *
 * - Área de un jefe: la unidad más alta de la cadena (la unidad y sus
 *   ancestros) que encabeza y que tiene sub-unidades.
 */
class ReglasOrganigrama
{
    /**
     * Área de $jefe a la que pertenece $unidad, o null si la unidad no
     * cuelga de ninguna área suya.
     */
    public function areaDe(User $jefe, UnidadOrganica $unidad): ?int
    {
        $area = null;
        $vistas = [];

        for ($u = $unidad; $u && ! isset($vistas[$u->id]); $u = $u->parent_id ? UnidadOrganica::find($u->parent_id) : null) {
            $vistas[$u->id] = true;

            if ((int) $u->jefe_id === (int) $jefe->id && $u->hijos()->exists()) {
                $area = (int) $u->id;
            }
        }

        return $area;
    }

    /**
     * ¿Puede $actor gestionar jefaturas (cambiar quién jefatura, mover jefes) en $unidad?
     * Admin: siempre. Jefe de área: solo en unidades que cuelgan de su área, sin contar
     * la unidad que él mismo encabeza como jefe de área.
     */
    public function puedeGestionarJefaturasEn(User $actor, UnidadOrganica $unidad): bool
    {
        if ($actor->hasRole('admin')) {
            return true;
        }

        $area = $this->areaDe($actor, $unidad);

        return $area !== null && (int) $area !== (int) $unidad->id;
    }

    /** ¿$user es jefe de área de la unidad $unidadId (la encabeza o encabeza un ancestro con sub-unidades)? */
    public function esJefeDeAreaDe(User $user, int $unidadId): bool
    {
        $unidad = UnidadOrganica::find($unidadId);

        return $unidad !== null && $this->areaDe($user, $unidad) !== null;
    }
}
