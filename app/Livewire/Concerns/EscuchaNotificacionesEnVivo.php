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
 */
trait EscuchaNotificacionesEnVivo
{
    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = Auth::id();

        return [
            "echo-notification:App.Models.User.{$id}" => '$refresh',
        ];
    }
}
