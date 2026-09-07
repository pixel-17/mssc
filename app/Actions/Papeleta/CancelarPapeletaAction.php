<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;

/**
 * Paso 1: cancelable solo por el trabajador dueño, solo mientras siga
 * en PENDIENTE_JEFE. Una vez que el tiempo corre (cualquier otro
 * estado) ya no se puede cancelar.
 *
 * "Si cancelación y autorización chocan casi al mismo tiempo, gana
 * quien confirme primero en BD": por eso se relee la fila con
 * lockForUpdate() dentro de la transacción en lugar de confiar en el
 * modelo ya cargado en memoria — evita cancelar algo que un jefe
 * acaba de aprobar en el mismo instante.
 */
class CancelarPapeletaAction
{
    public function ejecutar(Papeleta $papeleta, User $trabajador): Papeleta
    {
        if ($papeleta->trabajador_id !== $trabajador->id) {
            throw new PapeletaException('No puedes cancelar una papeleta que no es tuya.');
        }

        return DB::transaction(function () use ($papeleta, $trabajador) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(PendienteJefe::class)) {
                throw new PapeletaException('Esta papeleta ya fue resuelta y no se puede cancelar.');
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->estado = new Cancelada($actual);
            $actual->cancelada_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $trabajador->id,
                'actor_tipo' => 'trabajador',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
            ]);

            return $actual;
        });
    }
}
