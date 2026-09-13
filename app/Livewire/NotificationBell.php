<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
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
 * Tiempo real vía Reverb: PapeletaNotification agregó el canal
 * 'broadcast', que empuja cada notificación nueva al canal privado
 * `App.Models.User.{id}` (routes/channels.php). Este componente
 * escucha ese evento (ver getListeners()) y simplemente se re-renderiza
 * — no hace falta pintar nada a mano en JS, Livewire vuelve a pedir
 * las notificaciones de la base de datos al instante. Reemplaza al
 * wire:poll.30s que había antes.
 */
class NotificationBell extends Component
{
    use EscuchaNotificacionesEnVivo;

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
