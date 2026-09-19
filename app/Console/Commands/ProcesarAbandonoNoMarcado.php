<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Services\CalculadorDiasHabiles;
use App\Services\DeterminadorFinDeTurno;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\FinalizadoSinRetorno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Paso 5: "Turno vence sin marcación -> FINALIZADO_SIN_RETORNO
 * (ABANDONO_NO_MARCADO), notifica a jefe y RRHH, misma ventana de 48h,
 * mismo visto bueno humano." El estado destino es terminal (a
 * diferencia de RETORNO_PENDIENTE_SUSTENTO), por lo que la ventana de
 * 48h no es un estado propio: se modela igual que el sustento, con
 * requiere_visto_bueno=true y regularizacion_fecha_limite, para que el
 * job de vencimiento de sustentos y este puedan compartir la misma
 * bandeja de "pendiente de decisión humana" sin inventar un 14vo estado.
 *
 * No decide sobre RETORNO_PENDIENTE_SUSTENTO: esa transición
 * (RetornoPendienteSustento::class -> FinalizadoSinRetorno::class,
 * "abandono gana sobre sustento vencido") es un juicio humano —
 * requiere que alguien determine que el retorno registrado no fue
 * real — y vive en MarcarAbandonoSobreRetornoPendienteAction, no en
 * este job automático.
 */
class ProcesarAbandonoNoMarcado extends Command
{
    protected $signature = 'papeletas:procesar-abandono-no-marcado';

    protected $description = 'Marca FINALIZADO_SIN_RETORNO (abandono) las papeletas en curso cuyo turno/día terminó sin que el trabajador marcara retorno.';

    public function handle(DeterminadorFinDeTurno $finDeTurno, CalculadorDiasHabiles $diasHabiles, NotificarPapeletaService $notificar): int
    {
        // Mismo plazo que el sustento de Salud (Paso 5: "misma ventana
        // de 48h"), en horas hábiles y con la misma clave de config por
        // defecto para no duplicar el número mágico en dos lugares.
        $horasVentana = (int) Configuracion::valorDe('SUSTENTO_HORAS_HABILES', 48);

        Papeleta::whereState('estado', AutorizadaYCorriendo::class)
            ->whereDoesntHave('retorno')
            ->chunkById(100, function ($lote) use ($finDeTurno, $diasHabiles, $horasVentana, $notificar) {
                foreach ($lote as $candidata) {
                    if (! $finDeTurno->yaTermino($candidata)) {
                        continue;
                    }

                    try {
                        $papeleta = DB::transaction(function () use ($candidata, $finDeTurno, $diasHabiles, $horasVentana) {
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

                            $actual->transicionarA(FinalizadoSinRetorno::class);
                            $actual->causa_finalizacion_sin_retorno = 'abandono_no_marcado';
                            $actual->requiere_visto_bueno = true;
                            $actual->regularizacion_fecha_limite = $diasHabiles->agregarHorasHabiles(now(), $horasVentana);
                            $actual->save();

                            HistorialPapeleta::create([
                                'papeleta_id' => $actual->id,
                                'actor_id' => null,
                                'actor_tipo' => 'sistema',
                                'estado_anterior' => $estadoAnterior,
                                'estado_nuevo' => class_basename($actual->estado),
                                'justificacion' => 'Abandono no marcado: turno/día terminó sin registro de retorno. Notificado a jefe y RRHH.',
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
