<?php

namespace App\Actions\Usuario;

use App\Exceptions\PapeletaException;
use App\Models\User;

/**
 * Contraparte de AsignarJefeAdicionalAction. Mismo criterio de
 * autorización: admin, jefe de área del trabajador, o cualquier jefe
 * inmediato (automático o adicional) que el trabajador ya tenga.
 *
 * A propósito NO valida papeletas en curso: si el trabajador tiene una
 * papeleta PENDIENTE_JEFE esperando a este jefe en particular, sigue
 * pudiendo decidirla igual mientras esté PENDIENTE (la decisión ya
 * quedó fotografiada en papeletas.jefe_inmediato_id al crearse, ver
 * Paso 1); quitar el jefe adicional aquí solo afecta papeletas nuevas.
 */
class DesasignarJefeAdicionalAction
{
    public function ejecutar(User $trabajador, User $jefeAQuitar, User $solicitadoPor): void
    {
        $puedeQuitar = $solicitadoPor->hasRole('admin')
            || $solicitadoPor->id === $trabajador->jefe_area_id
            || $solicitadoPor->esJefeInmediatoDe($trabajador);

        if (! $puedeQuitar) {
            throw new PapeletaException('No tienes permiso para quitar jefes inmediatos de este trabajador.');
        }

        $existia = $trabajador->jefesInmediatosAdicionales()
            ->where('users.id', $jefeAQuitar->id)
            ->exists();

        if (! $existia) {
            throw new PapeletaException('Ese usuario no es jefe inmediato adicional de este trabajador.');
        }

        $trabajador->jefesInmediatosAdicionales()->detach($jefeAQuitar->id);
    }
}
