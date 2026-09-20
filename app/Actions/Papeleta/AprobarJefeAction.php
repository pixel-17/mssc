<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
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
 *
 * La fila se relee con lockForUpdate() dentro de la transacción (mismo
 * criterio que CancelarPapeletaAction) para que dos aprobaciones casi
 * simultáneas del mismo jefe, o una aprobación que choca con una
 * cancelación del trabajador, no puedan pisarse: quien confirme
 * primero en BD gana, y el segundo ve el estado ya actualizado.
 */
class AprobarJefeAction
{
    use ExigeDecisorAjeno;

    public function __construct(
        private RrhhHorarioService $horarioRrhh,
        private NotificarPapeletaService $notificar,
    ) {}

    public function ejecutar(Papeleta $papeleta, User $quienAprueba, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $quienAprueba, $actorTipo) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $quienAprueba);

            if (! $actual->estado->equals(PendienteJefe::class) && ! $actual->estado->equals(ObservadaPorJefe::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
            }

            $estadoAnterior = class_basename($actual->estado);
            $rrhhEnHorario = $this->horarioRrhh->estaEnHorarioAhora();

            $actual->resuelto_por_jefe_id = $quienAprueba->id;
            $actual->jefe_resuelto_at = now();

            if ($rrhhEnHorario) {
                $actual->transicionarA(PendienteRrhh::class);
            } else {
                $actual->transicionarA(AutorizadaYCorriendo::class);
                $actual->autorizado_con_rrhh_fuera_horario = true;
                $actual->hora_salida_real = now();
                $actual->revision_posthoc_estado = 'pendiente'; // Paso 4: obligatoria al día siguiente
            }

            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $quienAprueba->id,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
            ]);

            return $actual;
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
