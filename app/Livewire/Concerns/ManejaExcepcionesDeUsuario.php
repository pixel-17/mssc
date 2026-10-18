<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Exceptions\UsuarioException;
use Closure;

/**
 * Centraliza el patrón que se repetía, casi idéntico, en más de diez
 * componentes Livewire (OrganigramaArbol, UnidadModal,
 * UsuarioAdminIndex, BajaConReemplazo, UnidadOrganicaForm...):
 *
 *     try {
 *         ...acción que puede violar una regla de negocio...
 *     } catch (UsuarioException $e) {
 *         $this->algo = $e->getMessage();
 *         return;
 *     }
 *
 * UsuarioException representa una violación de regla de negocio que el
 * usuario puede corregir (no un error del sistema), por eso cada
 * componente decide dónde mostrar el mensaje (una propiedad pública,
 * session()->flash, $this->addError...) en vez de dejar que la excepción
 * suba sin control.
 *
 * No cubre los catches de OrganigramaArbol que acumulan varios mensajes
 * de distintas acciones para elegir cuál mostrar (p. ej.
 * proponerMovimientoJefe): ese es un flujo de control distinto, no el
 * mismo boilerplate repetido, y forzarlo aquí sería cambiar su
 * comportamiento en vez de solo destilar la repetición.
 */
trait ManejaExcepcionesDeUsuario
{
    /**
     * Ejecuta $accion. Si lanza UsuarioException, se la pasa a $alFallar
     * (quien decide dónde mostrar el mensaje) y devuelve false. Si
     * $accion termina sin lanzar, devuelve true.
     *
     * Uso típico en un método de acción del componente:
     *
     *     if (! $this->intentar(
     *         fn () => $estado->desactivar(auth()->user(), $usuario),
     *         fn (UsuarioException $e) => $this->error = $e->getMessage(),
     *     )) {
     *         return;
     *     }
     */
    protected function intentar(Closure $accion, Closure $alFallar): bool
    {
        try {
            $accion();

            return true;
        } catch (UsuarioException $e) {
            $alFallar($e);

            return false;
        }
    }
}
