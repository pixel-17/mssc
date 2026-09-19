<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Avisa por Reverb al trabajador (canal privado App.Models.User.{id},
 * el mismo de las notificaciones, ya autorizado en routes/channels.php)
 * que su horario cambió, para que "Mi calendario" se repinte solo sin
 * recargar la página.
 *
 * ShouldBroadcastNow y no ShouldBroadcast: con QUEUE_CONNECTION=database
 * el aviso quedaría esperando a un worker y dejaría de ser tiempo real.
 */
class HorarioActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    /**
     * Punto de entrada para los servicios que escriben turnos.
     *
     * - Espera al commit: si se llama dentro de una transacción, el
     *   navegador no debe refrescar antes de que los datos existan.
     * - Nunca rompe el guardado: si Reverb está caído se reporta el
     *   error y el horario igual queda guardado (el calendario tiene
     *   además un refresco periódico de respaldo).
     */
    public static function notificar(int ...$userIds): void
    {
        $ids = array_values(array_unique($userIds));

        DB::afterCommit(function () use ($ids) {
            foreach ($ids as $id) {
                try {
                    event(new self($id));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
