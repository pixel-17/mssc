<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorización para crear y ver otros `users`. RRHH NO aparece aquí a
 * propósito: no crea ni aprueba usuarios, solo consume dashboards y
 * reportes (ver conversación de diseño).
 *
 * "Jefe de Área" y "Jefe Inmediato" no son roles de Spatie — son
 * posiciones derivadas (ver UnidadOrganica::jefeInmediato()/jefeArea()
 * y User::esJefeInmediatoDe()), igual que en PapeletaPolicy.
 */
class UserPolicy
{
    /**
     * ¿Puede $creator crear un usuario dentro de la unidad orgánica
     * $unidadDestinoId? Cubre tanto crear un Trabajador como crear un
     * Jefe Inmediato (asignándolo como jefe_id de una sub-unidad).
     */
    public function crearEnUnidad(User $creator, int $unidadDestinoId): bool
    {
        if ($creator->hasRole('admin')) {
            return true;
        }

        return $this->esJefeDeAreaDe($creator, $unidadDestinoId);
    }

    /**
     * ¿Puede $creator crear un Trabajador y asignarse a sí mismo como
     * jefe inmediato adicional? Cualquier jefe inmediato (automático o
     * adicional) de al menos un trabajador puede hacerlo, sin importar
     * el área — igual que hoy cualquier jefe de unidad ya puede.
     */
    public function crearTrabajadorPropio(User $creator): bool
    {
        if ($creator->hasRole('admin')) {
            return true;
        }

        return $creator->unidadesQueEncabeza()->exists()
            || \App\Models\User::where('jefe_inmediato_id', $creator->id)->exists()
            || $creator->trabajadoresAdicionales()->exists();
    }

    /**
     * ¿Puede $viewer ver el perfil/listado de $target?
     */
    public function view(User $viewer, User $target): bool
    {
        if ($viewer->hasRole('admin') || $viewer->hasRole('rrhh')) {
            return true;
        }

        if ($viewer->id === $target->id) {
            return true;
        }

        // Jefe de Área: ve a todos los de su área (unidad + sub-unidades).
        if ($target->unidad_organica_id
            && $this->esJefeDeAreaDe($viewer, $target->unidad_organica_id)) {
            return true;
        }

        // Jefe Inmediato: ve solo a sus propios trabajadores (automáticos
        // + adicionales), no a todo el área.
        return $viewer->esJefeInmediatoDe($target);
    }

    /**
     * ¿$user encabeza la unidad orgánica $unidadId, o alguna unidad
     * ancestro de ella? (jefe de área aplica también a sub-unidades).
     */
    protected function esJefeDeAreaDe(User $user, int $unidadId): bool
    {
        $unidad = \App\Models\UnidadOrganica::find($unidadId);

        while ($unidad) {
            if ($unidad->jefe_id === $user->id) {
                return true;
            }
            $unidad = $unidad->parent_id
                ? \App\Models\UnidadOrganica::find($unidad->parent_id)
                : null;
        }

        return false;
    }
}
