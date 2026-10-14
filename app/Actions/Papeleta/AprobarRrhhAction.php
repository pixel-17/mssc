<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
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
 *
 * La fila se relee con lockForUpdate() dentro de la transacción (mismo
 * criterio que CancelarPapeletaAction) para que dos decisiones casi
 * simultáneas de RRHH no puedan pisarse.
 */
class AprobarRrhhAction
{
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $rrhh) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->estado->equals(PendienteRrhh::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
            }

            $actual->transicionarConHistorial(
                AutorizadaYCorriendo::class,
                $rrhh->id,
                'rrhh',
                cambios: function (Papeleta $p) use ($rrhh) {
                    $p->resuelto_por_rrhh_id = $rrhh->id;
                    $p->rrhh_resuelto_at = now();
                    $p->hora_salida_real = now();
                },
            );

            return $actual;
        });

        $this->notificar->puedeSalir($papeleta);

        return $papeleta;
    }
}
