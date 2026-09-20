<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;

/**
 * El trabajador responde a una observación del jefe: siempre por
 * escrito, y con un adjunto además si el jefe lo exigió al observar
 * (observacion_requiere_adjunto). La papeleta vuelve a PENDIENTE_JEFE con
 * el reloj reiniciado, para que el jefe decida de nuevo. Responder NO
 * aprueba nada (misma regla de legitimidad que los sustentos): solo
 * reabre la decisión del jefe.
 *
 * La respuesta y el adjunto quedan en la papeleta y los ven jefe y RRHH
 * (por si más adelante la papeleta les llega a decidir).
 *
 * Acción del propio dueño: aquí NO aplica ExigeDecisorAjeno (es al revés).
 * La fila se relee con lockForUpdate() para que una cancelación, una
 * decisión del jefe o el job de vencimiento que ya cambiaron el estado
 * no se pisen.
 */
class SubsanarObservacionAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $trabajador, string $respuesta, ?string $adjuntoPath = null): Papeleta
    {
        if ((int) $papeleta->trabajador_id !== (int) $trabajador->id) {
            throw new PapeletaException('No puedes responder una papeleta que no es tuya.');
        }

        $respuesta = trim($respuesta);

        if ($respuesta === '') {
            throw new PapeletaException('Debes escribir tu respuesta a la observación.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $trabajador, $respuesta, $adjuntoPath) {
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->equals(ObservadaPorJefe::class)) {
                throw new PapeletaException('Esta papeleta ya no tiene una observación pendiente de respuesta.');
            }

            if ($actual->observacion_requiere_adjunto && ! $adjuntoPath) {
                throw new PapeletaException('Tu jefe pidió que adjuntes un archivo junto con tu respuesta.');
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->transicionarA(PendienteJefe::class);
            $actual->observacion_respuesta = $respuesta;
            $actual->observacion_adjunto_path = $adjuntoPath ?? $actual->observacion_adjunto_path;
            $actual->observacion_subsanada_at = now();
            $actual->jefe_resuelto_at = null;
            $actual->escalado_jefe_area_at = null;
            $actual->reloj_jefe_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $trabajador->id,
                'actor_tipo' => 'trabajador',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => $respuesta,
            ]);

            return $actual;
        });

        $this->notificar->observacionRespondida($papeleta);

        return $papeleta;
    }
}
