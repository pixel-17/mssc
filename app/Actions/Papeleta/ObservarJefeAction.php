<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Rechazada;
use Illuminate\Support\Facades\DB;

/**
 * Observación del Jefe Inmediato. Tope configurable en `configuraciones`
 * (clave TOPE_OBSERVACIONES) -> al alcanzarlo, rechazo automático.
 * El trabajador debe subir sustento Y el jefe debe dar visto bueno
 * explícito para volver a PENDIENTE_JEFE con el reloj reiniciado.
 *
 * La fila se relee con lockForUpdate() dentro de la transacción (mismo
 * criterio que CancelarPapeletaAction). Además de proteger la
 * transición contra una decisión que ya se confirmó en BD, esto evita
 * que dos observaciones casi simultáneas lean el mismo
 * contador_observaciones_jefe desechado e incrementen desde el mismo
 * valor viejo: la segunda transacción espera a que la primera libere
 * el lock y ve el contador ya actualizado.
 */
class ObservarJefeAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $jefe, string $comentario, string $actorTipo = 'jefe_inmediato'): Papeleta
    {
        $papeleta = DB::transaction(function () use ($papeleta, $jefe, $comentario, $actorTipo) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(PendienteJefe::class)) {
                throw new PapeletaException('Esta papeleta ya no está pendiente de decisión del jefe.');
            }

            $estadoAnterior = class_basename($actual->estado);
            $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES', 3);

            $actual->contador_observaciones_jefe++;

            if ($actual->contador_observaciones_jefe >= $tope) {
                $actual->estado = new Rechazada($actual);
                $actual->rechazada_por_id = $jefe->id;
                $actual->motivo_rechazo = "Tope de {$tope} observaciones alcanzado.";
            } else {
                $actual->estado = new ObservadaPorJefe($actual);
            }

            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $jefe->id,
                'actor_tipo' => $actorTipo,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => $comentario,
            ]);

            return $actual;
        });

        if ($papeleta->estado->equals(Rechazada::class)) {
            $this->notificar->rechazada($papeleta);
        } else {
            $this->notificar->observadaPorJefe($papeleta);
        }

        return $papeleta;
    }
}
