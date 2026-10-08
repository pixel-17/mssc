<?php

namespace App\Console\Commands;

use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Services\DeterminadorFinDeTurno;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\Services\AbrirJustificacion;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turno vence sin marcación de retorno. La causa `abandono_no_marcado` se
 * conserva siempre (Papeleta::esAbandono()); el estado depende del motivo:
 *  - exige justificación (Salud) -> EnJustificacion: el trabajador puede
 *    presentarla, incluso al día siguiente, dentro del plazo del motivo.
 *    Aprobada -> Cerrada (abandono justificado); rechazada o vencida ->
 *    Finalizada (con descuento).
 *  - descuenta (Particular)      -> Finalizada.
 *  - el resto                    -> Cerrada.
 * Jefe y RRHH reciben el aviso "Turno finalizado sin marcar retorno" y
 * /rrhh/abandonos queda como lista informativa.
 *
 * Solo el sistema marca abandono: ni el jefe ni RRHH pueden hacerlo a mano.
 * No toca papeletas con retorno ya registrado.
 */
class ProcesarAbandonoNoMarcado extends Command
{
    protected $signature = 'papeletas:procesar-abandono-no-marcado';

    protected $description = 'Cierra con causa de abandono las papeletas en curso cuyo turno/día terminó sin que el trabajador marcara retorno.';

    public function handle(DeterminadorFinDeTurno $finDeTurno, NotificarPapeletaService $notificar): int
    {
        Papeleta::whereState('estado', AutorizadaYCorriendo::class)
            ->whereDoesntHave('retorno')
            ->chunkById(100, function ($lote) use ($finDeTurno, $notificar) {
                foreach ($lote as $candidata) {
                    if (! $finDeTurno->yaTermino($candidata)) {
                        continue;
                    }

                    try {
                        $papeleta = DB::transaction(function () use ($candidata, $finDeTurno) {
                            // Relectura bajo lock: si el trabajador marcó retorno
                            // (o el jefe lo hizo manual) mientras corría el lote,
                            // ya no es abandono.
                            $actual = Papeleta::whereKey($candidata->id)->lockForUpdate()->first();

                            if (! $actual
                                || ! $actual->estado->equals(AutorizadaYCorriendo::class)
                                || $actual->retorno()->exists()
                                || ! $finDeTurno->yaTermino($actual)) {
                                return null;
                            }

                            $estadoAnterior = class_basename($actual->estado);

                            $consecuencia = $actual->motivo->consecuenciaAlTerminar();

                            $actual->transicionarA(match ($consecuencia) {
                                'justificar' => EnJustificacion::class,
                                'descuenta' => Finalizada::class,
                                default => Cerrada::class,
                            });
                            $actual->causa_finalizacion_sin_retorno = 'abandono_no_marcado';
                            $actual->requiere_visto_bueno = false;
                            $actual->regularizacion_fecha_limite = null;
                            $actual->save();

                            if ($consecuencia === 'justificar') {
                                app(AbrirJustificacion::class)->para($actual, now());
                            }

                            HistorialPapeleta::create([
                                'papeleta_id' => $actual->id,
                                'actor_id' => null,
                                'actor_tipo' => 'sistema',
                                'estado_anterior' => $estadoAnterior,
                                'estado_nuevo' => class_basename($actual->estado),
                                'justificacion' => $consecuencia === 'justificar'
                                    ? 'Abandono no marcado: turno/día terminó sin registro de retorno. Queda en justificación dentro del plazo del motivo. Notificado a jefe y RRHH.'
                                    : 'Abandono no marcado: turno/día terminó sin registro de retorno. Papeleta cerrada. Notificado a jefe y RRHH.',
                            ]);

                            return $actual;
                        });

                        // Notificación web push + in-app a jefe_inmediato_id y a
                        // RRHH, fuera de la transacción (ver NotificarPapeletaService).
                        if ($papeleta) {
                            $notificar->abandonoNoMarcado($papeleta);
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        return self::SUCCESS;
    }
}
