<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Elimina una unidad orgánica solo si está vacía.
 *
 * Las FK de parent_id y users.unidad_organica_id son nullOnDelete: borrar
 * una unidad con gente o sub-unidades las dejaría huérfanas en silencio
 * (raíces sin jefe de área, personas sin unidad). Por eso aquí se bloquea
 * y se pide moverlas o desactivar la unidad.
 */
class EliminarUnidadOrganicaAction
{
    /** @throws UsuarioException */
    public function ejecutar(User $actor, UnidadOrganica $unidad): void
    {
        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede eliminar unidades orgánicas.');
        }

        if ($unidad->hijos()->exists()) {
            throw new UsuarioException('La unidad tiene sub-unidades. Muévelas o elimínalas primero.');
        }

        if ($unidad->miembros()->exists()) {
            throw new UsuarioException('La unidad tiene personas asignadas. Muévelas a otra unidad o desactiva la unidad.');
        }

        try {
            $unidad->delete();
        } catch (QueryException $e) {
            // Referencias que no son nullOnDelete (p. ej. papeletas): se conserva el historial.
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            throw new UsuarioException('La unidad tiene historial asociado y no puede borrarse. Desactívala.');
        }
    }
}
