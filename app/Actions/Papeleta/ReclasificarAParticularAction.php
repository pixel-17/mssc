<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ReclasificadoAParticular;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Support\Facades\DB;

/**
 * "Solo cambia el motivo, horas intactas" — usada en dos casos del
 * documento: sustento de Salud vencido sin nada presentado (Paso 5) y
 * subsanación de Emergencia observada sin resolver a tiempo (Paso 6).
 * Actor null = job automático (Console\Commands); si un humano la
 * dispara explícitamente, se pasa su id.
 */
class ReclasificarAParticularAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, ?int $actorId, string $actorTipo, string $justificacion): Papeleta
    {
        if (! $papeleta->estado->equals(RetornoPendienteSustento::class)) {
            throw new PapeletaException('Solo se puede reclasificar una papeleta en espera de sustento.');
        }

        $motivoParticular = Motivo::where('es_destino_reclasificacion', true)->first();

        if (! $motivoParticular) {
            throw new PapeletaException('No hay un motivo configurado como destino de reclasificación (es_destino_reclasificacion).');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $motivoParticular, $actorId, $actorTipo, $justificacion) {
            $estadoAnterior = class_basename($papeleta->estado);
            $motivoAnteriorId = $papeleta->motivo_id;

            $papeleta->motivo_original_id = $motivoAnteriorId;
            $papeleta->motivo_id = $motivoParticular->id;
            $papeleta->estado = new ReclasificadoAParticular($papeleta);
            $papeleta->requiere_visto_bueno = false;
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $actorId,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'motivo_anterior_id' => $motivoAnteriorId,
                'motivo_nuevo_id' => $motivoParticular->id,
                'justificacion' => $justificacion,
            ]);

            return $papeleta;
        });

        $this->notificar->reclasificadaAParticular($papeleta);

        return $papeleta;
    }
}
