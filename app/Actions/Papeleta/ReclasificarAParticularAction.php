<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\ReclasificadoAParticular;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Support\Facades\DB;

/**
 * "Solo cambia el motivo, horas intactas" — usada en dos casos del
 * documento: sustento de Salud vencido sin nada presentado (Paso 5,
 * estado RetornoPendienteSustento) y subsanación de Emergencia
 * observada sin resolver a tiempo (Paso 6, estado AutorizadaYCorriendo
 * o ya Cerrada — la revisión post-hoc de Emergencia no bloquea que el
 * trabajador retorne y cierre normalmente mientras se resuelve).
 * Actor null = job automático (Console\Commands); si un humano la
 * dispara explícitamente, se pasa su id.
 */
class ReclasificarAParticularAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    /**
     * @param  bool  $exigirSubsanacionEmergenciaVencida  Para el job automático de Paso 6: bajo lock
     *                                                     vuelve a comprobar que la Emergencia sigue
     *                                                     observada y con plazo vencido (jefe/RRHH
     *                                                     pudo resolverla mientras corría el lote).
     */
    public function ejecutar(
        Papeleta $papeleta,
        ?int $actorId,
        string $actorTipo,
        string $justificacion,
        bool $exigirSubsanacionEmergenciaVencida = false,
    ): Papeleta {
        $motivoParticular = Motivo::where('es_destino_reclasificacion', true)->first();

        if (! $motivoParticular) {
            throw new PapeletaException('No hay un motivo configurado como destino de reclasificación (es_destino_reclasificacion).');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $motivoParticular, $actorId, $actorTipo, $justificacion, $exigirSubsanacionEmergenciaVencida) {
            // Relectura bajo lock (mismo criterio que el resto de Actions):
            // el estado recibido puede estar viejo si un humano decidió
            // entre la lectura y este punto.
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $esCasoSalud = $actual->estado->equals(RetornoPendienteSustento::class);
            $esCasoEmergencia = $actual->es_emergencia
                && $actual->estado->equals(AutorizadaYCorriendo::class, Cerrada::class);

            if (! $esCasoSalud && ! $esCasoEmergencia) {
                throw new PapeletaException('Esta papeleta no está en un estado que se pueda reclasificar a Particular.');
            }

            if ($exigirSubsanacionEmergenciaVencida && ! $this->subsanacionEmergenciaVencida($actual)) {
                throw new PapeletaException('La subsanación de la Emergencia ya no está vencida u observada.');
            }

            $estadoAnterior = class_basename($actual->estado);
            $motivoAnteriorId = $actual->motivo_id;

            $actual->motivo_original_id = $motivoAnteriorId;
            $actual->motivo_id = $motivoParticular->id;
            $actual->transicionarA(ReclasificadoAParticular::class);
            $actual->requiere_visto_bueno = false;
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $actorId,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'motivo_anterior_id' => $motivoAnteriorId,
                'motivo_nuevo_id' => $motivoParticular->id,
                'justificacion' => $justificacion,
            ]);

            return $actual;
        });

        $this->notificar->reclasificadaAParticular($papeleta);

        return $papeleta;
    }

    private function subsanacionEmergenciaVencida(Papeleta $p): bool
    {
        return $p->es_emergencia
            && $p->subsanacion_emergencia_fecha_limite !== null
            && $p->subsanacion_emergencia_fecha_limite->lte(now())
            && ($p->visto_bueno_jefe_emergencia === 'observado' || $p->visto_bueno_rrhh_emergencia === 'observado');
    }
}
