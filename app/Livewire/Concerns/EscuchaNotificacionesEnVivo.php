<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Cualquier bandeja/listado (Trabajador, Jefe, RRHH, Dashboard) que
 * necesite verse actualizada en tiempo real sin recargar la página usa
 * este trait. Se apoya en el mismo mecanismo que NotificationBell:
 * cada vez que NotificarPapeletaService envía una PapeletaNotification
 * a este usuario, esta llega por el canal privado
 * `App.Models.User.{id}` (Reverb) y el componente se vuelve a
 * renderizar solo, trayendo datos frescos de la base de datos.
 *
 * No decide "qué cambió" ni pinta nada por JS a propósito: es más
 * simple y más confiable re-consultar (las queries de estas bandejas
 * son livianas) que tratar de parchear el estado a mano en el cliente.
 *
 * Además del refresh, dispara el evento de navegador
 * 'notificacion-sonido' para que resources/js/notification-sound.js
 * reproduzca el tono (afinado, ver ese archivo) sin que este trait
 * necesite saber nada de audio.
 */
trait EscuchaNotificacionesEnVivo
{
    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = Auth::id();

        $escuchas = [
            "echo-notification:App.Models.User.{$id}" => 'refrescarPorNotificacionEnVivo',
        ];

        // Además de las notificaciones (que solo llegan a quien
        // corresponde), cada cambio de una papeleta que este usuario ve
        // (ver PapeletaActualizada) vuelve a renderizar la bandeja: así
        // el estado se ve en vivo aunque nadie haya sido notificado.
        if ($this->escuchaCambiosDeEstado()) {
            $escuchas["echo-private:App.Models.User.{$id},PapeletaActualizada"] = 'refrescarPorCambioDeEstado';
        }

        return $escuchas;
    }

    /** Los componentes que no muestran estados (la campana) lo sobrescriben en false. */
    protected function escuchaCambiosDeEstado(): bool
    {
        return true;
    }

    /** Sin cuerpo a propósito: la petición de Livewire ya vuelve a ejecutar render(). */
    public function refrescarPorCambioDeEstado(): void {}

    public function refrescarPorNotificacionEnVivo(): void
    {
        // Llamar a este método (en vez de '$refresh' plano) ya provoca
        // el re-render normal de Livewire; solo se añade el dispatch
        // para que el front pueda reaccionar (sonido) sin acoplarse
        // a la lógica de datos de cada bandeja.
        $this->dispatch('notificacion-sonido');
    }
}
