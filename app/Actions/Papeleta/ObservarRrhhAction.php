<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

/**
 * Observación de RRHH. Contador independiente del de jefe
 * (contador_observaciones_rrhh, clave TOPE_OBSERVACIONES_RRHH) —
 * al alcanzar el tope, rechazo automático sin intervención humana
 * adicional (mismo criterio que ObservarJefeAction).
 *
 * A diferencia de la observación del jefe, esta SIEMPRE vuelve al
 * jefe (nunca al trabajador) — ver ReconocerObservacionRrhhAction.
 */
class ObservarRrhhAction
{
    public function ejecutar(Papeleta $papeleta, User $rrhh, string $comentario): Papeleta
    {
        if (! $papeleta->estado->equals(PendienteRrhh::class)) {
            throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
        }

        return DB::transaction(function () use ($papeleta, $rrhh, $comentario) {
            $estadoAnterior = class_basename($papeleta->estado);
            $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES_RRHH', 3);

            $papeleta->contador_observaciones_rrhh++;

            if ($papeleta->contador_observaciones_rrhh >= $tope) {
                $papeleta->estado = new Rechazada($papeleta);
                $papeleta->rechazada_por_id = $rrhh->id;
                $papeleta->motivo_rechazo = "Tope de {$tope} observaciones de RRHH alcanzado.";
            } else {
                $papeleta->estado = new ObservadaPorRrhh($papeleta);
            }

            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $comentario,
            ]);

            return $papeleta;
        });
    }
}
