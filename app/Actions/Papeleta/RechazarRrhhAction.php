<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
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
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh, string $motivo): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $motivo) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->estado->equals(PendienteRrhh::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
            }

            $actual->transicionarConHistorial(
                Rechazada::class,
                $rrhh->id,
                'rrhh',
                $motivo,
                function (Papeleta $p) use ($rrhh, $motivo) {
                    $p->rechazada_por_id = $rrhh->id;
                    $p->motivo_rechazo = $motivo;
                },
            );

            return $actual;
        });

        $this->notificar->rechazada($papeleta);

        return $papeleta;
    }
}
