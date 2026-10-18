<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use Carbon\Carbon;

/**
 * Bloqueo suave de revisión de papeletas para RRHH.
 *
 * Permite marcar que un usuario está revisando una papeleta, liberar
 * ese bloqueo al tomar una decisión, y consultar quién la está revisando.
 * El bloqueo es indicativo (no impide la acción), solo se muestra un aviso
 * en la vista cuando otro revisor ya tiene la papeleta abierta.
 */
class BloqueoRevisionService
{
    /**
     * Intenta marcar la papeleta como en revisión por el usuario dado.
     *
     * Si ya está siendo revisada por otro usuario, NO sobreescribe el bloqueo
     * y retorna false. Si está libre o la tiene el mismo usuario, actualiza y
     * retorna true.
     */
    public function marcar(Papeleta $papeleta, User $usuario): bool
    {
        // Si ya la tiene otro, no sobreescribir.
        if (
            $papeleta->en_revision_por_id &&
            $papeleta->en_revision_por_id !== $usuario->id
        ) {
            return false;
        }

        $papeleta->update([
            'en_revision_por_id' => $usuario->id,
            'en_revision_desde' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * Libera el bloqueo de revisión de la papeleta.
     *
     * Solo libera si el bloqueo pertenece al usuario que ejecuta la acción,
     * para no interferir con otro revisor que haya tomado la papeleta.
     */
    public function liberar(Papeleta $papeleta, User $usuario): void
    {
        if ($papeleta->en_revision_por_id === $usuario->id) {
            $papeleta->update([
                'en_revision_por_id' => null,
                'en_revision_desde' => null,
            ]);
        }
    }

    /**
     * Retorna el nombre del revisor actual, o null si no hay bloqueo activo.
     */
    public function nombreRevisor(Papeleta $papeleta): ?string
    {
        return $papeleta->enRevisionPor?->name;
    }
}
