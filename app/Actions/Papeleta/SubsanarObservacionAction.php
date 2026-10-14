<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
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

            $actual->transicionarConHistorial(
                PendienteJefe::class,
                $trabajador->id,
                'trabajador',
                $respuesta,
                function (Papeleta $p) use ($respuesta, $adjuntoPath) {
                    $p->observacion_respuesta = $respuesta;
                    $p->observacion_adjunto_path = $adjuntoPath;
                    $p->observacion_subsanada_at = now();
                    $p->jefe_resuelto_at = null;
                    $p->reloj_jefe_at = now();
                },
            );

            return $actual;
        });

        $this->notificar->observacionRespondida($papeleta);

        return $papeleta;
    }
}
