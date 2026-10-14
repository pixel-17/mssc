<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Facades\DB;

/**
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

            // Observó: el trabajador debe responder primero (la papeleta vuelve
            // a PENDIENTE_JEFE al hacerlo). Mientras tanto solo puede rechazar.
            if ($actual->estado->equals(ObservadaPorJefe::class)) {
                throw new PapeletaException('Esta papeleta espera la respuesta del trabajador a tu observación: aprueba cuando responda o recházala.');
            }

            $rrhhEnHorario = $this->horarioRrhh->estaEnHorarioAhora();

            $actual->resuelto_por_jefe_id = $quienAprueba->id;
            $actual->jefe_resuelto_at = now();

            $actual->transicionarConHistorial(
                $rrhhEnHorario ? PendienteRrhh::class : AutorizadaYCorriendo::class,
                $quienAprueba->id,
                $actorTipo,
                cambios: function (Papeleta $p) use ($rrhhEnHorario) {
                    if (! $rrhhEnHorario) {
                        $p->autorizado_con_rrhh_fuera_horario = true;
                        $p->hora_salida_real = now();
                        $p->revision_posthoc_estado = 'pendiente'; // Paso 4: obligatoria al día siguiente
                    }
                },
            );

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
