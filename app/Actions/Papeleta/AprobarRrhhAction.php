<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Facades\DB;

/**
 * Paso 3: RRHH aprueba. Decide sin escalar a nadie más — este Action
 * solo existe para el caso en que la papeleta llegó a PENDIENTE_RRHH
 * porque RRHH estaba en horario (si RRHH estaba fuera de horario,
 * AprobarJefeAction ya la autorizó directo, ver Paso 2).
 *
 * hora_salida_real se fija aquí e inmutable a partir de este punto
 * (no se vuelve a escribir en ninguna transición posterior).
 */
class AprobarRrhhAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh): Papeleta
    {
        if (! $papeleta->estado->equals(PendienteRrhh::class)) {
            throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $rrhh) {
            $estadoAnterior = class_basename($papeleta->estado);

            $papeleta->estado = new AutorizadaYCorriendo($papeleta);
            $papeleta->resuelto_por_rrhh_id = $rrhh->id;
            $papeleta->rrhh_resuelto_at = now();
            $papeleta->hora_salida_real = now();
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
            ]);

            return $papeleta;
        });

        $this->notificar->puedeSalir($papeleta);

        return $papeleta;
    }
}
