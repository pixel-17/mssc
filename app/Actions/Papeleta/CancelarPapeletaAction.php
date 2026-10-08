<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Facades\DB;

/**
 * Cancelable solo por el trabajador dueño y SOLO antes de AUTORIZADA_Y_CORRIENDO
 * (a partir de ahí ya salió y no hay vuelta atrás).
 *
 * - PENDIENTE_JEFE / OBSERVADA_POR_JEFE: siempre.
 * - PENDIENTE_RRHH / OBSERVADA_POR_RRHH: solo si RRHH está en horario. Fuera
 *   de horario el sistema puede autorizar la papeleta por cierre de RRHH
 *   (ver ProcesarVencimientosPapeletas) y nadie atiende una cancelación:
 *   en ese momento ya no se puede cancelar. Cuando RRHH no trabaja y el
 *   jefe aprueba, la papeleta pasa directo a AUTORIZADA_Y_CORRIENDO, así
 *   que tampoco hay ventana de cancelación.
 *
 * "Si cancelación y autorización chocan casi al mismo tiempo, gana
 * quien confirme primero en BD": por eso se relee la fila con
 * lockForUpdate() dentro de la transacción en lugar de confiar en el
 * modelo ya cargado en memoria — evita cancelar algo que un jefe o RRHH
 * acaba de aprobar en el mismo instante.
 */
class CancelarPapeletaAction
{
    public function __construct(private RrhhHorarioService $horarioRrhh) {}

    public function ejecutar(Papeleta $papeleta, User $trabajador): Papeleta
    {
        if ($papeleta->trabajador_id !== $trabajador->id) {
            throw new PapeletaException('No puedes cancelar una papeleta que no es tuya.');
        }

        return DB::transaction(function () use ($papeleta, $trabajador) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $enFaseJefe = $actual->estado->equals(PendienteJefe::class, ObservadaPorJefe::class);
            $enFaseRrhh = $actual->estado->equals(PendienteRrhh::class, ObservadaPorRrhh::class);

            if (! $enFaseJefe && ! $enFaseRrhh) {
                throw new PapeletaException('Esta papeleta ya fue autorizada o resuelta y no se puede cancelar.');
            }

            if ($enFaseRrhh && ! $this->horarioRrhh->estaEnHorarioAhora()) {
                throw new PapeletaException('RRHH está fuera de horario: no se puede cancelar la papeleta mientras espera a RRHH.');
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->transicionarA(Cancelada::class);
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
