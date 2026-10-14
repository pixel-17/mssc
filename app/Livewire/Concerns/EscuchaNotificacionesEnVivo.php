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

        // Un solo canal por componente. Antes las bandejas escuchaban ambos y
        // cada transición con aviso las re-renderizaba dos veces (y sonaba el
        // tono dos veces: ya lo dispara la campana). PapeletaActualizada cubre
        // TODO cambio de papeleta, notifique o no, así que las bandejas se
        // quedan solo con él; la campana solo con las notificaciones.
        if ($this->escuchaCambiosDeEstado()) {
            return [
                "echo-private:App.Models.User.{$id},PapeletaActualizada" => 'refrescarPorCambioDeEstado',
            ];
        }

        return [
            "echo-notification:App.Models.User.{$id}" => 'refrescarPorNotificacionEnVivo',
        ];
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
