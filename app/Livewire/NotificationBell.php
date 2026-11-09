<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Models\User;
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

    /** La campana ya se actualiza con las notificaciones; los cambios de estado no la afectan. */
    protected function escuchaCambiosDeEstado(): bool
    {
        return false;
    }

    public function marcarComoLeida(string $id): void
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $notificacion = $usuario->notifications()->whereKey($id)->first();

        $notificacion?->markAsRead();
    }

    public function marcarTodasComoLeidas(): void
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $usuario->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        /** @var User $usuario */
        $usuario = Auth::user();

        $noLeidas = $usuario->unreadNotifications()->count();

        // Sincroniza el icono del PWA (Badging API, ver resources/js/
        // app-badge.js) cada vez que la campana se re-renderiza: carga
        // inicial, al marcar como leída, o al llegar una notificación en
        // vivo. Así el icono no se queda desactualizado mientras la
        // pestaña sigue abierta (public/sw.js cubre el caso con la app
        // cerrada).
        $this->dispatch('contador-notificaciones-actualizado', cantidad: $noLeidas);

        return view('livewire.notification-bell', [
            'notificaciones' => $usuario->notifications()->latest()->take($this->porMostrar)->get(),
            'noLeidas' => $noLeidas,
        ]);
    }
}