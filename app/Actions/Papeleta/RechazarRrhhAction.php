<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

class RechazarRrhhAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh, string $motivo): Papeleta
    {
        if (! $papeleta->estado->equals(PendienteRrhh::class)) {
            throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $motivo) {
            $estadoAnterior = class_basename($papeleta->estado);

            $papeleta->estado = new Rechazada($papeleta);
            $papeleta->rechazada_por_id = $rrhh->id;
            $papeleta->motivo_rechazo = $motivo;
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $motivo,
            ]);

            return $papeleta;
        });

        $this->notificar->rechazada($papeleta);

        return $papeleta;
    }
}
