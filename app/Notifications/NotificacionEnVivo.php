<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Base de las notificaciones con entrega en vivo (PapeletaNotification y
 * AlertaOrganizacionalNotification). Aquí vive lo que antes estaba copiado
 * en ambas: los tres canales ('database' es el registro de verdad;
 * 'webpush' y 'broadcast' son entrega inmediata adicional), el payload
 * común y el armado del mensaje push.
 *
 * Cada subclase solo declara lo que la distingue: datos extra del payload,
 * etiqueta del botón, tag de agrupación y datos del push.
 *
 * ShouldQueue: ver PapeletaNotification (requiere un worker corriendo).
 */
abstract class NotificacionEnVivo extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Reintentos ante fallos transitorios (red, Reverb caído, push
     * rechazado). El canal 'database' casi nunca falla; webpush y
     * broadcast son los candidatos. Con 3 intentos y backoff creciente
     * se cubre la mayoría de caídas breves sin saturar la cola.
     * El primer reintento es a los 10 s, el segundo al minuto, el
     * tercero a los 5 min; tras eso el job va a failed_jobs.
     */
    public int $tries = 3;

    /** @return int[] segundos de espera entre cada intento */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(
        protected readonly string $tipo,
        protected readonly string $titulo,
        protected readonly string $mensaje,
        protected readonly ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class, 'broadcast'];
    }

    /** Claves propias de la subclase que se suman al payload común. @return array<string, mixed> */
    protected function datosExtra(): array
    {
        return [];
    }

    /** Identificador con el que el navegador agrupa/reemplaza pushes del mismo tema. */
    abstract protected function etiquetaPush(): string;

    /** Texto del botón del push cuando hay url. */
    abstract protected function textoAccion(): string;

    /** Datos que viajan en el push (el service worker los usa al hacer clic). @return array<string, mixed> */
    abstract protected function datosPush(): array;

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'tipo' => $this->tipo,
            ...$this->datosExtra(),
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'url' => $this->url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
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
            ...$this->payload(),
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $mensaje = (new WebPushMessage)
            ->title($this->titulo)
            ->body($this->mensaje)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag($this->etiquetaPush());

        $datos = $this->datosPush();

        if ($datos !== []) {
            $mensaje->data($datos);
        }

        if ($this->url) {
            $mensaje->action($this->textoAccion(), 'ver');
        }

        return $mensaje;
    }
}