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
 * "Solo cambia el motivo, horas intactas" — usada cuando el sustento de
 * Salud vence sin nada presentado (Paso 5, estado
 * RetornoPendienteSustento).
 * Actor null = job automático (Console\Commands); si un humano la
 * dispara explícitamente, se pasa su id.
 */
class ReclasificarAParticularAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(
        Papeleta $papeleta,
        ?int $actorId,
        string $actorTipo,
        string $justificacion,
    ): Papeleta {
        $motivoParticular = Motivo::where('es_destino_reclasificacion', true)->first();

        if (! $motivoParticular) {
            throw new PapeletaException('No hay un motivo configurado como destino de reclasificación (es_destino_reclasificacion).');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $motivoParticular, $actorId, $actorTipo, $justificacion) {
            // Relectura bajo lock (mismo criterio que el resto de Actions):
            // el estado recibido puede estar viejo si un humano decidió
            // entre la lectura y este punto.
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(RetornoPendienteSustento::class)) {
                throw new PapeletaException('Esta papeleta no está en un estado que se pueda reclasificar a Particular.');
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
}
