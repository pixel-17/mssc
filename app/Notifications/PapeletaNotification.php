<?php

namespace App\Notifications;

use App\Models\Papeleta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación única para todos los eventos de papeleta (ver
 * NotificarPapeletaService, que es el único punto de verdad sobre
 * "qué evento notifica a quién"). Esta clase solo sabe entregar, nunca
 * decide destinatarios ni reglas de negocio.
 *
 * Tres canales en paralelo, tal como documenta la migración de
 * `notifications`: 'database' es el registro de verdad (bandeja
 * in-app, persistente, se puede leer offline); 'webpush' es entrega
 * inmediata adicional y puede fallar (navegador cerrado, suscripción
 * vencida) sin que eso afecte el registro in-app; 'broadcast' empuja
 * el mismo evento por el websocket de Reverb al canal privado
 * `App.Models.User.{id}` (routes/channels.php) para que la campana y
 * cualquier bandeja/listado abierto en el navegador se actualicen al
 * instante, sin poll ni recargar.
 *
 * ShouldQueue: el envío de push/broadcast nunca debe bloquear la
 * petición HTTP que dispara la transición de estado
 * (QUEUE_CONNECTION=database ya está configurado en el proyecto —
 * para que el broadcast sea instantáneo hace falta un worker corriendo,
 * ver `php artisan queue:work`).
 */
class PapeletaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Papeleta $papeleta,
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

    /**
     * Mismo payload que toDatabase(), más 'id' y 'created_at' para que
     * el front (NotificationBell) pueda pintar la notificación al
     * vuelo sin ir a buscarla de nuevo a la base de datos.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'tipo' => $this->tipo,
            'papeleta_id' => $this->papeleta->id,
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
            'papeleta_id' => $this->papeleta->id,
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
            ->tag("papeleta-{$this->papeleta->id}")
            ->data([
                'tipo' => $this->tipo,
                'papeleta_id' => $this->papeleta->id,
                'url' => $this->url,
            ]);

        if ($this->url) {
            $mensaje->action('Ver papeleta', 'ver');
        }

        return $mensaje;
    }
}
