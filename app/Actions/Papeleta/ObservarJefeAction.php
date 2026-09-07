<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

/**
 * Observación del Jefe Inmediato. Tope configurable en `configuraciones`
 * (clave TOPE_OBSERVACIONES) -> al alcanzarlo, rechazo automático.
 * El trabajador debe subir sustento Y el jefe debe dar visto bueno
 * explícito para volver a PENDIENTE_JEFE con el reloj reiniciado.
 */
class ObservarJefeAction
{
    public function ejecutar(Papeleta $papeleta, User $jefe, string $comentario, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        if (! $papeleta->estado->equals(PendienteJefe::class)) {
            throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
        }

        return DB::transaction(function () use ($papeleta, $jefe, $comentario, $actorTipo) {
            $estadoAnterior = class_basename($papeleta->estado);
            $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES', 3);

            $papeleta->contador_observaciones_jefe++;

            if ($papeleta->contador_observaciones_jefe >= $tope) {
                $papeleta->estado = new Rechazada($papeleta);
                $papeleta->rechazada_por_id = $jefe->id;
                $papeleta->motivo_rechazo = "Tope de {$tope} observaciones alcanzado.";
            } else {
                $papeleta->estado = new ObservadaPorJefe($papeleta);
            }

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
