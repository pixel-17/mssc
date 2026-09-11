<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\ReclasificarAParticularAction;
use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use Illuminate\Console\Command;

/**
 * Paso 6: si jefe y/o RRHH observaron una Emergencia y el trabajador no
 * subsanó (o subsanó pero el revisor no volvió a decidir) antes de
 * subsanacion_emergencia_fecha_limite, se reclasifica a Particular
 * automáticamente — "solo cambia el motivo, horas intactas".
 *
 * Corre sobre AutorizadaYCorriendo Y Cerrada (ver comentario en
 * PapeletaState::config()): la revisión post-hoc de Emergencia no
 * bloquea que el trabajador ya haya retornado y cerrado normalmente.
 */
class ProcesarSubsanacionEmergenciaVencida extends Command
{
    protected $signature = 'papeletas:procesar-subsanacion-emergencia-vencida';

    protected $description = 'Reclasifica a Particular las Emergencias observadas cuyo plazo de subsanación venció sin resolverse.';

    public function handle(ReclasificarAParticularAction $reclasificar): int
    {
        Papeleta::where('es_emergencia', true)
            ->whereNotNull('subsanacion_emergencia_fecha_limite')
            ->where('subsanacion_emergencia_fecha_limite', '<=', now())
            ->whereState('estado', [AutorizadaYCorriendo::class, Cerrada::class])
            ->where(function ($q) {
                $q->where('visto_bueno_jefe_emergencia', 'observado')
                    ->orWhere('visto_bueno_rrhh_emergencia', 'observado');
            })
            ->each(function (Papeleta $papeleta) use ($reclasificar) {
                $reclasificar->ejecutar(
                    $papeleta,
                    actorId: null,
                    actorTipo: 'sistema',
                    justificacion: 'Reclasificado automáticamente: subsanación de Emergencia no resuelta dentro del plazo de días hábiles configurado.',
                );
            });

        return self::SUCCESS;
    }
}
