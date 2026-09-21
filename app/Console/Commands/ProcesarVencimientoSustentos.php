<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\ReclasificarAParticularAction;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Paso 5, motivo Salud. Corre cada minuto (ver routes/console.php),
 * separado de ProcesarVencimientosPapeletas porque esta ventana (48h
 * hábiles desde el retorno) no tiene nada que ver con el SLA de
 * minutos del jefe ni con el fin de turno/día.
 *
 * - Sustento vencido y NUNCA se subió nada ('pendiente') -> reclasifica
 *   a Particular automáticamente, sin intervención humana (no hay nada
 *   que revisar).
 * - Sustento vencido pero SÍ hay un archivo sin revisar ('presentado')
 *   -> el sistema NO cierra solo (Paso 8): marca
 *   papeleta.requiere_visto_bueno = true para que aparezca en la
 *   bandeja de jefe/RRHH, y deja que RevisarSustentoAction decida.
 *
 * Defensivo: ambas queries exigen que la papeleta siga en
 * RetornoPendienteSustento antes de actuar. Un Sustento puede quedar
 * huérfano si la papeleta sale de ese estado por otra vía que no
 * cierre el Sustento asociado (p. ej. MarcarAbandonoSobreRetornoPendienteAction
 * la mueve a FinalizadoSinRetorno sin tocar `sustentos`). Sin este
 * filtro, ReclasificarAParticularAction lanza PapeletaException sobre
 * ese registro y, al no estar capturada dentro del each(), tumba el
 * resto del job para ese ciclo.
 */
class ProcesarVencimientoSustentos extends Command
{
    protected $signature = 'papeletas:procesar-vencimiento-sustentos';

    protected $description = 'Reclasifica a Particular los sustentos de Salud vencidos sin presentar; marca visto bueno pendiente si hay archivo sin revisar.';

    public function handle(ReclasificarAParticularAction $reclasificar, NotificarPapeletaService $notificar): int
    {
        // ReclasificarAParticularAction ya notifica al trabajador
        // internamente (mismo Action que usa el flujo humano de
        // reclasificación), no hay que duplicar el envío aquí.
        Sustento::where('estado', 'pendiente')
            ->where('fecha_limite', '<=', now())
            ->whereHas('papeleta', fn ($q) => $q->whereState('estado', RetornoPendienteSustento::class))
            ->with('papeleta')
            ->chunkById(100, function ($lote) use ($reclasificar) {
                foreach ($lote as $sustento) {
                    try {
                        DB::transaction(function () use ($sustento, $reclasificar) {
                            // Mismo orden de locks que RevisarSustentoAction: primero la
                            // papeleta y luego el sustento. Al revés (como estaba: escribir
                            // el sustento y recién después bloquear la papeleta) un revisor
                            // y este job podían quedar en deadlock.
                            $papeleta = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->first();
                            $actual = Sustento::whereKey($sustento->id)->lockForUpdate()->first();

                            // Se relee todo: entre la lectura del lote y este punto el
                            // trabajador pudo presentar el archivo a tiempo (antes se le
                            // pisaba su 'presentado' con 'vencido') o un humano resolver.
                            if (! $papeleta
                                || ! $actual
                                || $actual->estado !== 'pendiente'
                                || $actual->fecha_limite->isFuture()
                                || ! $papeleta->estado->equals(RetornoPendienteSustento::class)) {
                                return;
                            }

                            $actual->estado = 'vencido';
                            $actual->save();

                            // Si un humano la resuelve justo ahora lanza PapeletaException y el
                            // rollback deja el sustento como estaba.
                            $reclasificar->ejecutar(
                                $papeleta,
                                actorId: null,
                                actorTipo: 'sistema',
                                justificacion: 'Reclasificado automáticamente: sustento de Salud vencido sin presentar (48h hábiles).',
                            );
                        });
                    } catch (PapeletaException) {
                        // Ya la resolvió un humano: se omite.
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        Sustento::where('estado', 'presentado')
            ->where('fecha_limite', '<=', now())
            ->whereHas('papeleta', fn ($q) => $q->where('requiere_visto_bueno', false)
                ->whereState('estado', RetornoPendienteSustento::class))
            ->with('papeleta')
            ->chunkById(100, function ($lote) use ($notificar) {
                foreach ($lote as $sustento) {
                    try {
                        $papeleta = DB::transaction(function () use ($sustento) {
                            $papeleta = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->first();

                            if (! $papeleta
                                || $papeleta->requiere_visto_bueno
                                || ! $papeleta->estado->equals(RetornoPendienteSustento::class)) {
                                return null;
                            }

                            $papeleta->requiere_visto_bueno = true;
                            $papeleta->save();

                            HistorialPapeleta::create([
                                'papeleta_id' => $papeleta->id,
                                'actor_id' => null,
                                'actor_tipo' => 'sistema',
                                'estado_anterior' => class_basename($papeleta->estado),
                                'estado_nuevo' => class_basename($papeleta->estado),
                                'justificacion' => 'Sustento presentado sin revisar: venció el plazo de 48h hábiles sin decisión humana.',
                            ]);

                            return $papeleta;
                        });

                        if ($papeleta) {
                            $notificar->sustentoSinRevisar($papeleta);
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        return self::SUCCESS;
    }
}
