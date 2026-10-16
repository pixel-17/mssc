<?php

namespace App\Policies;

use App\Actions\Organigrama\ReglasOrganigrama;
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
     * ¿Puede $creator crear un Trabajador en su propia unidad? Cualquier
     * jefe inmediato de al menos un trabajador puede hacerlo.
     */
    public function crearTrabajadorPropio(User $creator): bool
    {
        // Sin bypass de admin: el admin no tiene trabajadores ni jefes
        // inmediatos. Su alta de cuentas va por usuarios-admin.
        return $creator->esJefeDeEquipo();
    }

    /**
     * ¿Puede $creator crear ALGÚN usuario desde la vía de jefes, sin importar en
     * qué unidad? Sirve para mostrar/ocultar el enlace "Trabajadores" en el menú,
     * sin repetir la lógica de crearEnUnidad()/crearTrabajadorPropio().
     */
    public function puedeCrearAlgo(User $creator): bool
    {
        // Sin bypass de admin: esta es la vía de alta de los jefes (Trabajadores /
        // usuarios.crear). La admin no tiene trabajadores propios; sus altas
        // (incluido otro admin) van por Cuentas de usuario (usuarios.crear)
        // o por el organigrama.
        if ($creator->hasRole('admin')) {
            return false;
        }

        return $creator->unidadesQueEncabeza()->exists()
            || $this->crearTrabajadorPropio($creator);
    }

    /**
     * ¿Puede $editor editar los datos de $target? Mismo alcance que ya
     * tienen para crear (crearEnUnidad/crearTrabajadorPropio), pero
     * aplicado a un usuario existente:
     *
     * - admin: cualquiera.
     * - Jefe de Área: cualquiera de su área (unidad que encabeza + sub-unidades).
     * - Jefe Inmediato "puro": solo sus propios trabajadores, y nunca a un
     *   Jefe de Área ni a un admin.
     * - Nadie salvo un admin edita a un usuario con rol admin o rrhh: quien
     *   edita puede cambiar el correo y, con "olvidé mi contraseña", tomar
     *   la cuenta. Dejarlo abierto rompería la separación jefe / RR. HH.
     */
    public function editar(User $editor, User $target): bool
    {
        if ($editor->hasRole('admin')) {
            return true;
        }

        if ($target->hasAnyRole(['admin', 'rrhh']) || $editor->id === $target->id) {
            return false;
        }

        if ($target->unidad_organica_id
            && $this->esJefeDeAreaDe($editor, $target->unidad_organica_id)) {
            return true;
        }

        return $editor->esJefeInmediatoDe($target) && ! $target->esJefeTitular();
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

        // Jefe Inmediato: ve solo a sus propios trabajadores (de cualquier
        // origen), no a todo el área.
        return $viewer->esJefeInmediatoDe($target);
    }

    /**
     * ¿$user encabeza la unidad orgánica $unidadId, o alguna unidad
     * ancestro de ella? (jefe de área aplica también a sub-unidades).
     * La regla vive en ReglasOrganigrama, compartida con las Actions de mover.
     */
    protected function esJefeDeAreaDe(User $user, int $unidadId): bool
    {
        return app(ReglasOrganigrama::class)->esJefeDeAreaDe($user, $unidadId);
    }
}