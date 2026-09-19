<?php

namespace App\Events;

use App\Models\Papeleta;
use App\Models\User;
use App\Support\PapeletaEstadoPresentacion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * "La papeleta X cambió" — sin importar quién la movió ni si hubo
 * notificación. Es lo que permite ver el estado en vivo.
 *
 * Por qué no basta con PapeletaNotification: esa solo llega a quien
 * NotificarPapeletaService decide notificar (p. ej. al aprobar el jefe
 * con RRHH en horario, el trabajador no recibe nada; al cancelar el
 * trabajador, nadie). Este evento cubre TODO cambio y va a todos los
 * que ven la papeleta.
 *
 * Destinatarios (canales privados App.Models.User.{id}, ya autorizados
 * en routes/channels.php): el trabajador, su jefe inmediato (automático
 * y adicionales), el jefe de área, y RRHH/admin (bandeja y dashboard).
 *
 * ShouldBroadcastNow: con QUEUE_CONNECTION=database un ShouldBroadcast
 * normal esperaría a un worker y dejaría de ser tiempo real.
 */
class PapeletaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** Reverb/Pusher aceptan hasta 100 canales por publicación. */
    private const CANALES_POR_ENVIO = 50;

    /**
     * Papeletas con un afterCommit ya agendado en este ciclo. Una sola
     * acción de negocio suele tocar Papeleta + HistorialPapeleta (o
     * Retorno/Sustento) a la vez, y cada modelo llama notificar() por
     * su cuenta: sin esto, se dispararía el evento (y sus queries) dos
     * veces por el mismo cambio.
     *
     * @var array<int, true>
     */
    private static array $pendientes = [];

    /** @param  array<int, int>  $userIds */
    public function __construct(
        public int $papeletaId,
        public string $estado,
        public string $etiqueta,
        public array $userIds,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.'.$id),
            $this->userIds
        );
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'papeleta_id' => $this->papeletaId,
            'estado' => $this->estado,
            'etiqueta' => $this->etiqueta,
        ];
    }

    /**
     * Punto de entrada (lo llaman los modelos, ver Papeleta::booted).
     *
     * - Espera al commit: el navegador refresca al recibir el aviso y
     *   los datos (estado + historial) ya deben estar guardados.
     * - Lee la papeleta fresca dentro del callback, así el estado
     *   publicado es el confirmado.
     * - Nunca rompe la operación de negocio: si Reverb está caído se
     *   reporta y el flujo sigue.
     */
    public static function notificar(int $papeletaId): void
    {
        if (isset(self::$pendientes[$papeletaId])) {
            return;
        }

        self::$pendientes[$papeletaId] = true;

        // Si la transacción hace rollback el afterCommit nunca corre y la
        // marca quedaba viva: en comandos (un solo proceso, muchas
        // papeletas) esa papeleta no volvía a notificar jamás.
        DB::afterRollBack(function () use ($papeletaId) {
            unset(self::$pendientes[$papeletaId]);
        });

        DB::afterCommit(function () use ($papeletaId) {
            unset(self::$pendientes[$papeletaId]);

            try {
                // Antes del broadcast: quien re-renderiza por el aviso debe leer datos frescos.
                // Aislado: si la caché falla igual se emite el evento.
                try {
                    \App\Services\DashboardMetricsService::invalidar();
                } catch (Throwable $e) {
                    report($e);
                }

                $papeleta = Papeleta::with('trabajador.jefesInmediatosAdicionales')->find($papeletaId);

                if (! $papeleta) {
                    return;
                }

                $ids = collect([$papeleta->trabajador_id, $papeleta->jefe_inmediato_id, $papeleta->jefe_area_id])
                    ->merge($papeleta->trabajador?->jefesInmediatosAdicionales->pluck('id') ?? [])
                    ->merge(User::role(['rrhh', 'admin'])->pluck('id'))
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                [$etiqueta] = PapeletaEstadoPresentacion::para($papeleta->estado);

                foreach ($ids->chunk(self::CANALES_POR_ENVIO) as $grupo) {
                    event(new self($papeleta->id, class_basename($papeleta->estado), $etiqueta, $grupo->all()));
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
