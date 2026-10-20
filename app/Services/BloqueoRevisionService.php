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
     * Minutos antes de refrescar el bloqueo de un usuario que ya lo tenía.
     * Evita escribir (y por tanto disparar PapeletaActualizada) en cada
     * carga de la página: sin esto, el componente "en vivo" de jefe/RRHH
     * entra en un bucle de refresco — cada GET a show() volvía a marcar(),
     * eso guardaba la papeleta, el guardado emitía el evento, y el evento
     * disparaba un nuevo GET.
     */
    private const MINUTOS_RENOVACION = 2;

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

        // Ya la tiene este mismo usuario y el bloqueo sigue fresco: no hay
        // nada que escribir. Guardar aquí sin cambios reales en los datos
        // igual dispara el evento "en vivo" (Papeleta::booted -> saved),
        // así que solo se renueva pasado un rato.
        if (
            $papeleta->en_revision_por_id === $usuario->id
            && $papeleta->en_revision_desde
            && $papeleta->en_revision_desde->gt(Carbon::now()->subMinutes(self::MINUTOS_RENOVACION))
        ) {
            return true;
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
