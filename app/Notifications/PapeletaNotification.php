<?php

namespace App\Notifications;

use App\Models\Papeleta;

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
class PapeletaNotification extends NotificacionEnVivo
{
    public function __construct(
        private readonly Papeleta $papeleta,
        string $tipo,
        string $titulo,
        string $mensaje,
        ?string $url = null,
    ) {
        parent::__construct($tipo, $titulo, $mensaje, $url);
    }

    protected function datosExtra(): array
    {
        return ['papeleta_id' => $this->papeleta->id];
    }

    protected function etiquetaPush(): string
    {
        return "papeleta-{$this->papeleta->id}";
    }

    protected function textoAccion(): string
    {
        return 'Ver papeleta';
    }

    protected function datosPush(): array
    {
        return [
            'tipo' => $this->tipo,
            'papeleta_id' => $this->papeleta->id,
            'url' => $this->url,
        ];
    }
}
