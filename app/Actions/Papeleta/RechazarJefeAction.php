<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
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
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $jefe, string $motivo, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $jefe, $motivo, $actorTipo) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $jefe);

            if (! $actual->estado->equals(PendienteJefe::class) && ! $actual->estado->equals(ObservadaPorJefe::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
            }

            $actual->transicionarConHistorial(
                Rechazada::class,
                $jefe->id,
                $actorTipo,
                $motivo,
                function (Papeleta $p) use ($jefe, $motivo) {
                    $p->rechazada_por_id = $jefe->id;
                    $p->motivo_rechazo = $motivo;
                },
            );

            return $actual;
        });

        $this->notificar->rechazada($papeleta);

        return $papeleta;
    }
}
