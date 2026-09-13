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

/**
 * La fila se relee con lockForUpdate() dentro de la transacción (mismo
 * criterio que CancelarPapeletaAction) para que un rechazo no pueda
 * pisar una decisión que ya se confirmó en BD un instante antes.
 */
class RechazarRrhhAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh, string $motivo): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $motivo) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(PendienteRrhh::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->estado = new Rechazada($actual);
            $actual->rechazada_por_id = $rrhh->id;
            $actual->motivo_rechazo = $motivo;
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
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
