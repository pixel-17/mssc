<?php

namespace App\Actions\Usuario;

use App\Exceptions\UsuarioException;
use App\Models\User;

/**
 * Activar / desactivar a un usuario. Única puerta: la usan la lista de
 * Usuarios, el formulario de edición y el panel de trabajador del
 * organigrama, para que las tres apliquen exactamente las mismas reglas
 * (antes el organigrama escribía `activo` directo y se saltaba todas).
 *
 * NUNCA se borra un usuario: papeletas.trabajador_id tiene cascadeOnDelete
 * y borrarlo se llevaría su historial. Se desactiva; EnsureUsuarioActivo y
 * el login ya lo dejan afuera.
 *
 * Reglas para desactivar (motivoParaNoDesactivar):
 * - No a uno mismo.
 * - No al único RR. HH. activo (sin él toda papeleta se autorizaría sola).
 * - No a quien es jefe inmediato de un turno (jefes_turno): reasignar antes.
 * - No a quien encabeza una unidad con sub-unidades activas (jefe de área).
 * - No a quien encabeza una unidad que todavía tiene personas activas:
 *   en 728 esas personas recibirían "no tiene un jefe inmediato activo" al
 *   crear papeletas, y en 276 las papeletas seguirían llegándole a alguien
 *   que ya no puede entrar. Hay que cambiar primero la jefatura.
 */
class CambiarEstadoUsuarioAction
{
    /** null = se puede desactivar; texto = el motivo por el que no. */
    public function motivoParaNoDesactivar(User $actor, User $usuario): ?string
    {
        if ($usuario->is($actor)) {
            return 'No puedes desactivar tu propia cuenta.';
        }

        if ($usuario->esUnicoRrhhActivo()) {
            return 'No puedes desactivar al único usuario de RR. HH. activo: sin él, toda papeleta aprobada por el jefe se autorizaría sola. Designa o reactiva primero a otra persona con rol RR. HH.';
        }

        if ($usuario->esJefeInmediatoDeAlgunTurno()) {
            return 'No puedes desactivar a este usuario: es jefe inmediato de un turno (MAÑANA/TARDE/NOCHE) en su unidad. Reasigna primero ese turno a otro jefe.';
        }

        $unidad = $usuario->unidadesQueEncabeza()
            ->where(fn ($q) => $q
                ->whereHas('miembros', fn ($m) => $m->where('activo', true)->whereKeyNot($usuario->getKey()))
                ->orWhereHas('hijos', fn ($h) => $h->where('activo', true)))
            ->first();

        if ($unidad && $unidad->hijos()->where('activo', true)->exists()) {
            return 'No puedes desactivar a este usuario: es jefe de área de «'.$unidad->nombre
                .'», que tiene sub-unidades a su cargo. Cambia primero la jefatura de esa unidad.';
        }

        if ($unidad) {
            $personas = $unidad->miembros()->where('activo', true)->whereKeyNot($usuario->getKey())->count();

            return 'No puedes desactivar a este usuario: es el jefe de «'.$unidad->nombre.'», que tiene '
                .$personas.' '.($personas === 1 ? 'persona activa' : 'personas activas')
                .'. Cambia primero la jefatura de esa unidad.';
        }

        return null;
    }

    /** @throws UsuarioException */
    public function desactivar(User $actor, User $usuario): User
    {
        $this->exigirAdmin($actor);

        if (! $usuario->activo) {
            return $usuario;
        }

        $motivo = $this->motivoParaNoDesactivar($actor, $usuario);

        if ($motivo !== null) {
            throw new UsuarioException($motivo);
        }

        $usuario->forceFill(['activo' => false])->save();
        $usuario->tokens()->delete();

        return $usuario;
    }

    /** @throws UsuarioException */
    public function reactivar(User $actor, User $usuario): User
    {
        $this->exigirAdmin($actor);

        if (! $usuario->activo) {
            $usuario->forceFill(['activo' => true])->save();
        }

        return $usuario;
    }

    private function exigirAdmin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw new UsuarioException('Solo un administrador puede activar o desactivar usuarios.');
        }
    }
}
