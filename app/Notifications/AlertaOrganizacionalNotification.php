<?php

namespace App\Notifications;

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
class AlertaOrganizacionalNotification extends NotificacionEnVivo
{
    protected function etiquetaPush(): string
    {
        return "alerta-organizacional-{$this->tipo}";
    }

    protected function textoAccion(): string
    {
        return 'Ver';
    }

    protected function datosPush(): array
    {
        return $this->url ? ['url' => $this->url] : [];
    }
}
