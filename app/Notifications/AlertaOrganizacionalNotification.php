<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación para avisos del organigrama que NO están ligados a una
 * Papeleta concreta (a diferencia de PapeletaNotification) — hoy solo
 * "falta jefe inmediato activo en un turno", pero sirve para cualquier
 * alerta futura del mismo tipo. Ver AlertaJefaturaService, único punto
 * de verdad de "cuándo se dispara y a quién llega".
 *
 * Mismos tres canales que PapeletaNotification, mismo motivo (ver esa
 * clase): 'database' es el registro de verdad, 'webpush' y
 * 'broadcast' son entrega inmediata adicional.
 */
class AlertaOrganizacionalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tipo,
        private readonly string $titulo,
        private readonly string $mensaje,
        private readonly ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class, 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo,
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
        ];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $mensaje = (new WebPushMessage)
            ->title($this->titulo)
            ->body($this->mensaje)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag("alerta-organizacional-{$this->tipo}");

        if ($this->url) {
            $mensaje->data(['url' => $this->url])->action('Ver', 'ver');
        }

        return $mensaje;
    }
}
