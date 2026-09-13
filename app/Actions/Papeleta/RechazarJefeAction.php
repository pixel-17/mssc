<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

/**
 * La fila se relee con lockForUpdate() dentro de la transacción (mismo
 * criterio que CancelarPapeletaAction) para que un rechazo no pueda
 * pisar una decisión que ya se confirmó en BD un instante antes.
 */
class RechazarJefeAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $jefe, string $motivo, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $jefe, $motivo, $actorTipo) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(PendienteJefe::class) && ! $actual->estado->equals(ObservadaPorJefe::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->estado = new Rechazada($actual);
            $actual->rechazada_por_id = $jefe->id;
            $actual->motivo_rechazo = $motivo;
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $jefe->id,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => $motivo,
            ]);

            return $actual;
        });

        $this->notificar->rechazada($papeleta);

        return $papeleta;
    }
}
