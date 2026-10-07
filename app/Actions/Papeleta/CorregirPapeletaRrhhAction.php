<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\ReclasificadoAParticular;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de datos de una papeleta YA resuelta (Cerrada o reclasificada
 * a Particular) por un error de registro: hora de retorno mal marcada o
 * motivo equivocado.
 *
 * No cambia el estado ni reabre el flujo: solo corrige el dato, recalcula el
 * descuento de refrigerio si cambió la hora y deja en el historial
 * (append-only) quién, cuándo, por qué y qué valores había antes y después.
 * La justificación es obligatoria.
 */
class CorregirPapeletaRrhhAction
{
    use ExigeDecisorAjeno;

    public const MIN_JUSTIFICACION = 10;

    public function __construct(private MarcarRetornoAction $marcarRetorno) {}

    public function ejecutar(Papeleta $papeleta, User $quienCorrige, ?Carbon $nuevaHoraRetorno, ?int $nuevoMotivoId, string $justificacion): Papeleta
    {
        $justificacion = trim($justificacion);

        if (mb_strlen($justificacion) < self::MIN_JUSTIFICACION) {
            throw new PapeletaException('La justificación de la corrección es obligatoria (mínimo '.self::MIN_JUSTIFICACION.' caracteres).');
        }

        if ($nuevaHoraRetorno === null && $nuevoMotivoId === null) {
            throw new PapeletaException('Indica qué se corrige: la hora de retorno, el motivo o ambos.');
        }

        return DB::transaction(function () use ($papeleta, $quienCorrige, $nuevaHoraRetorno, $nuevoMotivoId, $justificacion) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $quienCorrige);

            if (! $actual->estado->equals(Cerrada::class) && ! $actual->estado->equals(ReclasificadoAParticular::class)) {
                throw new PapeletaException('Solo se pueden corregir papeletas ya cerradas.');
            }

            $cambios = [];

            if ($nuevaHoraRetorno !== null) {
                $retorno = $actual->retorno;

                if (! $retorno || ! $actual->hora_salida_real) {
                    throw new PapeletaException('Esta papeleta no tiene salida y retorno registrados que corregir.');
                }

                if ($nuevaHoraRetorno->lessThan($actual->hora_salida_real)) {
                    throw new PapeletaException('La hora de retorno no puede ser anterior a la salida.');
                }

                if ($nuevaHoraRetorno->isFuture()) {
                    throw new PapeletaException('La hora de retorno no puede estar en el futuro.');
                }

                if (! $retorno->hora_servidor || ! $nuevaHoraRetorno->equalTo($retorno->hora_servidor)) {
                    $cambios['hora_retorno'] = [
                        'antes' => $retorno->hora_servidor?->toDateTimeString(),
                        'despues' => $nuevaHoraRetorno->toDateTimeString(),
                    ];

                    $retorno->hora_servidor = $nuevaHoraRetorno;
                    $retorno->save();

                    $refrigerioAntes = (int) $actual->descuento_refrigerio_minutos;
                    $actual->descuento_refrigerio_minutos = $this->marcarRetorno->calcularDescuentoRefrigerio($actual, $nuevaHoraRetorno);

                    if ($refrigerioAntes !== (int) $actual->descuento_refrigerio_minutos) {
                        $cambios['descuento_refrigerio_minutos'] = [
                            'antes' => $refrigerioAntes,
                            'despues' => (int) $actual->descuento_refrigerio_minutos,
                        ];
                    }
                }
            }

            $motivoAnteriorId = null;

            if ($nuevoMotivoId !== null && (int) $nuevoMotivoId !== (int) $actual->motivo_id) {
                $motivo = Motivo::where('activo', true)->find($nuevoMotivoId);

                if (! $motivo) {
                    throw new PapeletaException('El motivo elegido no existe o está inactivo.');
                }

                $motivoAnteriorId = $actual->motivo_id;
                $cambios['motivo_id'] = ['antes' => $motivoAnteriorId, 'despues' => $motivo->id];

                $actual->motivo_id = $motivo->id;
            }

            if ($cambios === []) {
                throw new PapeletaException('Los valores indicados son iguales a los que ya tiene la papeleta.');
            }

            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $quienCorrige->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => null,
                'estado_nuevo' => class_basename($actual->estado),
                'motivo_anterior_id' => $motivoAnteriorId,
                'motivo_nuevo_id' => $motivoAnteriorId ? $actual->motivo_id : null,
                'justificacion' => 'Corrección de RRHH: '.$justificacion,
                'metadata' => ['correccion_rrhh' => $cambios],
            ]);

            return $actual->refresh();
        });
    }
}
