<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;

/**
 * Cierra el ciclo de OBSERVADA_POR_RRHH: la observación de RRHH nunca
 * llega al trabajador (a diferencia de la del jefe). Es el jefe quien
 * la reconoce/coordina y reabre la papeleta en PENDIENTE_JEFE con el
 * reloj reiniciado, para que vuelva a pasar por su propia decisión
 * antes de re-enviarla a RRHH.
 */
class ReconocerObservacionRrhhAction
{
    use ExigeDecisorAjeno;

    public function ejecutar(Papeleta $papeleta, User $jefe, string $comentario, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        return DB::transaction(function () use ($papeleta, $jefe, $comentario, $actorTipo) {
            // Relectura bajo lock: sin ella, una observación ya reconocida
            // por otro jefe (o vencida por el job) se podía pisar.
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $jefe);

            if (! $actual->estado->equals(ObservadaPorRrhh::class)) {
                throw new PapeletaException('Esta papeleta no tiene una observación de RRHH pendiente de reconocer.');
            }

            $actual->transicionarConHistorial(
                PendienteJefe::class,
                $jefe->id,
                $actorTipo,
                $comentario,
                function (Papeleta $p) {
                    $p->jefe_resuelto_at = null;
                    $p->reloj_jefe_at = now();
                },
            );

            return $actual;
        });
    }
}
