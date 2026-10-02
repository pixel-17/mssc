<?php

namespace App\Actions\Organigrama;

use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Elimina una unidad orgánica solo si está vacía.
 *
 * Las FK de parent_id y users.unidad_organica_id son restrictOnDelete: la BD
 * misma impide borrar una unidad con gente o sub-unidades (antes eran
 * nullOnDelete y las dejaban huérfanas en silencio: raíces sin jefe de
 * área, personas sin unidad). Las validaciones de aquí dan el mensaje
 * claro; la FK respalda el caso de una carrera entre dos requests.
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
            // Violación de FK (23xxx): sub-unidades o personas que aparecieron
            // después del chequeo, o referencias que se conservan (p. ej. papeletas).
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            throw new UsuarioException('La unidad tiene sub-unidades, personas o historial asociado y no puede borrarse. Muévelas o desactiva la unidad.');
        }
    }
}
