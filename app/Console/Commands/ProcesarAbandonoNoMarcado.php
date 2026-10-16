<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\MarcarRetornoAction;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Retorno;
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
 *
 * Aunque no hubo marcación real, se crea igual un Retorno con
 * hora_servidor = fin_turno_at (marcado generado_por_abandono = true):
 * es la hora en que terminó su turno, y es la única fuente de verdad que
 * usan HistorialTrabajadorService / ReporteHorasAcumuladasService para
 * calcular cuánto tiempo estuvo afuera. Sin esto esas papeletas quedaban
 * sin horas calculables (whereHas('retorno') las excluía siempre). El
 * estado sigue siendo el que decida `consecuencia` más abajo: este
 * registro no cambia nada de la máquina de estados.
 */
class ProcesarAbandonoNoMarcado extends Command
{
    protected $signature = 'papeletas:procesar-abandono-no-marcado';

    protected $description = 'Cierra con causa de abandono las papeletas en curso cuyo turno/día terminó sin que el trabajador marcara retorno.';

    public function handle(DeterminadorFinDeTurno $finDeTurno, NotificarPapeletaService $notificar, MarcarRetornoAction $marcarRetorno): int
    {
        Papeleta::whereState('estado', AutorizadaYCorriendo::class)
            ->conTurnoTerminado()
            ->whereDoesntHave('retorno')
            ->chunkById(100, function ($lote) use ($finDeTurno, $notificar, $marcarRetorno) {
                foreach ($lote as $candidata) {
                    if (! $finDeTurno->yaTermino($candidata)) {
                        continue;
                    }

                    try {
                        $papeleta = DB::transaction(function () use ($candidata, $finDeTurno, $marcarRetorno) {
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

                            // Hora de fin para los cálculos de horas afuera: la del fin
                            // de turno/día, no "ahora" (el cron puede correr minutos
                            // después). whereDoesntHave('retorno') del query de arriba ya
                            // lo filtra, pero se revalida bajo lock por si acaso.
                            if (! $actual->retorno) {
                                $horaFin = $actual->fin_turno_at ?? now();

                                Retorno::create([
                                    'papeleta_id' => $actual->id,
                                    'hora_servidor' => $horaFin,
                                    'generado_por_abandono' => true,
                                ]);

                                // Mismo cálculo que un retorno marcado normalmente (Paso 7):
                                // si no se hace acá, el reporte de horas acumuladas cuenta
                                // de más en 276 cuando la ausencia cruza el bloque de
                                // almuerzo.
                                $actual->descuento_refrigerio_minutos = $marcarRetorno->calcularDescuentoRefrigerio($actual, $horaFin);
                            }

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