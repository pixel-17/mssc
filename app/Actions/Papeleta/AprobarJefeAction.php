<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Facades\DB;

/**
 * Paso 2: el Jefe Inmediato (o el Jefe de Área si ya escaló) aprueba.
 *
 * Si RRHH está en horario -> PENDIENTE_RRHH.
 * Si RRHH está fuera de horario -> directo a AUTORIZADA_Y_CORRIENDO,
 * marcada para revisión post-hoc obligatoria (Paso 4).
 */
class AprobarJefeAction
{
    public function __construct(
        private RrhhHorarioService $horarioRrhh,
        private NotificarPapeletaService $notificar,
    ) {}

    public function ejecutar(Papeleta $papeleta, User $quienAprueba, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        if (! $papeleta->estado->equals(PendienteJefe::class) && ! $papeleta->estado->equals(ObservadaPorJefe::class)) {
            throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $quienAprueba, $actorTipo) {
            $estadoAnterior = class_basename($papeleta->estado);
            $rrhhEnHorario = $this->horarioRrhh->estaEnHorarioAhora();

            $papeleta->resuelto_por_jefe_id = $quienAprueba->id;
            $papeleta->jefe_resuelto_at = now();

            if ($rrhhEnHorario) {
                $papeleta->estado = new PendienteRrhh($papeleta);
            } else {
                $papeleta->estado = new AutorizadaYCorriendo($papeleta);
                $papeleta->autorizado_con_rrhh_fuera_horario = true;
                $papeleta->hora_salida_real = now();
                $papeleta->revision_posthoc_estado = 'pendiente'; // Paso 4: obligatoria al día siguiente
            }

            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $quienAprueba->id,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
            ]);

            return $papeleta;
        });

        if ($papeleta->estado->equals(PendienteRrhh::class)) {
            $this->notificar->pendienteDeRrhh($papeleta);
        } else {
            $this->notificar->puedeSalir($papeleta);
            $this->notificar->revisionPosthocPendiente($papeleta);
        }

        return $papeleta;
    }
}
