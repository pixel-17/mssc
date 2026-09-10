<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\ReclasificarAParticularAction;
use App\Models\HistorialPapeleta;
use App\Models\Sustento;
use App\Services\NotificarPapeletaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            ->with('papeleta')
            ->each(function (Sustento $sustento) use ($reclasificar) {
                DB::transaction(function () use ($sustento, $reclasificar) {
                    $sustento->estado = 'vencido';
                    $sustento->save();

                    $reclasificar->ejecutar(
                        $sustento->papeleta,
                        actorId: null,
                        actorTipo: 'sistema',
                        justificacion: 'Reclasificado automáticamente: sustento de Salud vencido sin presentar (48h hábiles).',
                    );
                });
            });

        Sustento::where('estado', 'presentado')
            ->where('fecha_limite', '<=', now())
            ->whereHas('papeleta', fn ($q) => $q->where('requiere_visto_bueno', false))
            ->with('papeleta')
            ->each(function (Sustento $sustento) use ($notificar) {
                $papeleta = DB::transaction(function () use ($sustento) {
                    $papeleta = $sustento->papeleta;
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

                $notificar->sustentoSinRevisar($papeleta);
            });

        return self::SUCCESS;
    }
}
