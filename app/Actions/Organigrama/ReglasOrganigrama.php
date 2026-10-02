<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;

/**
 * Reglas del organigrama que antes estaban repetidas en las Actions de
 * mover y en UserPolicy. Un solo lugar para que el árbol, los formularios
 * y las políticas no puedan contradecirse:
 *
 * - Área de un jefe: la unidad más alta de la cadena (la unidad y sus
 *   ancestros) que encabeza y que tiene sub-unidades.
 * - Régimen: todos en una unidad comparten el de su jefe (ver
 *   UnidadOrganica::regimen()).
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

    /** ¿$user es jefe de área de la unidad $unidadId (la encabeza o encabeza un ancestro con sub-unidades)? */
    public function esJefeDeAreaDe(User $user, int $unidadId): bool
    {
        $unidad = UnidadOrganica::find($unidadId);

        return $unidad !== null && $this->areaDe($user, $unidad) !== null;
    }

    /**
     * El régimen de la persona debe coincidir con el de la unidad de
     * destino (una unidad vacía acepta cualquiera).
     *
     * @throws UsuarioException
     */
    public function exigirRegimenCompatible(User $persona, UnidadOrganica $destino): void
    {
        $regimenDestino = $destino->regimen();

        if ($regimenDestino !== null && $persona->regimen !== $regimenDestino) {
            throw new UsuarioException(
                'El régimen de '.$persona->nombre_completo.' ('.($persona->regimen ?? 'sin régimen')
                .') no coincide con el de la unidad de destino (régimen '.$regimenDestino.').'
            );
        }
    }
}
