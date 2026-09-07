<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

class RechazarJefeAction
{
    public function ejecutar(Papeleta $papeleta, User $jefe, string $motivo, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        if (! $papeleta->estado->equals(PendienteJefe::class) && ! $papeleta->estado->equals(ObservadaPorJefe::class)) {
            throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
        }

        return DB::transaction(function () use ($papeleta, $jefe, $motivo, $actorTipo) {
            $estadoAnterior = class_basename($papeleta->estado);

            $papeleta->estado = new Rechazada($papeleta);
            $papeleta->rechazada_por_id = $jefe->id;
            $papeleta->motivo_rechazo = $motivo;
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $jefe->id,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $motivo,
            ]);

            return $papeleta;
        });
    }
}
