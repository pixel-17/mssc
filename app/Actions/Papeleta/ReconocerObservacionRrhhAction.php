<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;

/**
 * Cierra el ciclo de OBSERVADA_POR_RRHH: la observación de RRHH nunca
 * llega al trabajador (a diferencia de la del jefe). Es el jefe quien
 * la reconoce/coordina y reabre la papeleta en PENDIENTE_JEFE con el
 * reloj reiniciado, para que vuelva a pasar por su propia decisión
 * antes de re-enviarla a RRHH.
 */
class ReconocerObservacionRrhhAction
{
    public function ejecutar(Papeleta $papeleta, User $jefe, string $comentario, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        if (! $papeleta->estado->equals(ObservadaPorRrhh::class)) {
            throw new PapeletaException('Esta papeleta no tiene una observación de RRHH pendiente de reconocer.');
        }

        return DB::transaction(function () use ($papeleta, $jefe, $comentario, $actorTipo) {
            $estadoAnterior = class_basename($papeleta->estado);

            $papeleta->estado = new PendienteJefe($papeleta);
            $papeleta->jefe_resuelto_at = null;
            $papeleta->escalado_jefe_area_at = null;
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $jefe->id,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $comentario,
            ]);

            return $papeleta;
        });
    }
}
