<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

/**
 * Observación de RRHH. Contador independiente del de jefe
 * (contador_observaciones_rrhh, clave TOPE_OBSERVACIONES_RRHH) —
 * al alcanzar el tope, rechazo automático sin intervención humana
 * adicional (mismo criterio que ObservarJefeAction).
 *
 * A diferencia de la observación del jefe, esta SIEMPRE vuelve al
 * jefe (nunca al trabajador) — ver ReconocerObservacionRrhhAction.
 *
 * La fila se relee con lockForUpdate() dentro de la transacción (mismo
 * criterio que ObservarJefeAction) para proteger tanto la transición
 * como el incremento de contador_observaciones_rrhh ante dos
 * observaciones casi simultáneas.
 */
class ObservarRrhhAction
{
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $rrhh, string $comentario): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $comentario) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->estado->equals(PendienteRrhh::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión de RRHH.');
            }

            $estadoAnterior = class_basename($actual->estado);
            $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES_RRHH', 3);

            $actual->contador_observaciones_rrhh++;

            if ($actual->contador_observaciones_rrhh >= $tope) {
                $actual->transicionarA(Rechazada::class);
                $actual->rechazada_por_id = $rrhh->id;
                $actual->motivo_rechazo = "Tope de {$tope} observaciones de RRHH alcanzado.";
            } else {
                $actual->transicionarA(ObservadaPorRrhh::class);
            }

            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => $comentario,
            ]);

            return $actual;
        });

        // Tope alcanzado -> rechazo automático, sí llega al trabajador.
        // Si no, la observación de RRHH nunca llega al trabajador: solo
        // al Jefe Inmediato (ver NotificarPapeletaService::observadaPorRrhh).
        if ($papeleta->estado->equals(Rechazada::class)) {
            $this->notificar->rechazada($papeleta);
        } else {
            $this->notificar->observadaPorRrhh($papeleta);
        }

        return $papeleta;
    }
}
