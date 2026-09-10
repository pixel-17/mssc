<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Bandeja in-app de notificaciones (campana en la barra de navegación).
 * Lee directo de la tabla `notifications` (Illuminate\Notifications\
 * DatabaseNotification) que ya escribe PapeletaNotification — este
 * componente es solo lectura/lectura-marcada, nunca decide a quién
 * notificar (eso vive en NotificarPapeletaService).
 *
 * wire:poll para verse "casi en tiempo real" sin depender de que el
 * usuario tenga permisos de push del navegador concedidos — el canal
 * in-app es "el registro de verdad" (ver comentario de la migración
 * de `notifications`), el push es solo un empujón adicional.
 */
class NotificationBell extends Component
{
    public int $porMostrar = 8;

    public function marcarComoLeida(string $id): void
    {
        $notificacion = Auth::user()->notifications()->whereKey($id)->first();

        $notificacion?->markAsRead();
    }

    public function marcarTodasComoLeidas(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        $usuario = Auth::user();

        return view('livewire.notification-bell', [
            'notificaciones' => $usuario->notifications()->latest()->take($this->porMostrar)->get(),
            'noLeidas' => $usuario->unreadNotifications()->count(),
        ]);
    }
}
