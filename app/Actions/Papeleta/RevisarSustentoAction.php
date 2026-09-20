<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Support\Facades\DB;

/**
 * Paso 8 / regla de legitimidad de adjuntos: subir el archivo NO cierra
 * el caso. El jefe o RRHH deben dar visto bueno explícito sobre el
 * adjunto antes de que la papeleta transite de RETORNO_PENDIENTE_SUSTENTO.
 *
 * El trabajador sube el archivo por separado (fuera de esta acción,
 * solo actualiza sustento.archivo_path/presentado_at/estado=presentado)
 * — esta acción es exclusivamente la decisión humana sobre ese archivo.
 */
class RevisarSustentoAction
{
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function aprobar(Sustento $sustento, User $revisor): Papeleta
    {
        return $this->resolver($sustento, $revisor, 'aprobado');
    }

    public function observar(Sustento $sustento, User $revisor, string $comentario): Papeleta
    {
        return $this->resolver($sustento, $revisor, 'observado', $comentario);
    }

    private function resolver(Sustento $sustento, User $revisor, string $resultado, ?string $comentario = null): Papeleta
    {
        $papeleta = DB::transaction(function () use ($sustento, $revisor, $resultado, $comentario) {
            // Orden de locks: primero la papeleta, luego el sustento (el mismo
            // que usan los jobs de vencimiento), para no provocar deadlocks.
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($sustento->papeleta_id)->lockForUpdate()->firstOrFail();
            /** @var Sustento $sustentoActual */
            $sustentoActual = Sustento::whereKey($sustento->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $revisor);

            if (! $actual->estado->equals(RetornoPendienteSustento::class)) {
                throw new PapeletaException('Esta papeleta ya no está esperando sustento.');
            }

            if ($sustentoActual->estado !== 'presentado') {
                throw new PapeletaException('Este sustento todavía no tiene un archivo presentado para revisar.');
            }

            $sustentoActual->estado = $resultado;
            $sustentoActual->revisado_por_id = $revisor->id;
            $sustentoActual->revisado_at = now();
            $sustentoActual->save();

            $estadoAnterior = class_basename($actual->estado);

            // Aprobado -> cierra. Observado -> el sustento sigue pendiente
            // de un nuevo archivo, la papeleta se queda en el mismo estado
            // hasta que venza (job de vencimiento) o el trabajador vuelva
            // a presentar antes de la fecha_limite.
            if ($resultado === 'aprobado') {
                $actual->transicionarA(Cerrada::class);
                $actual->save();
            } else {
                $sustentoActual->estado = 'pendiente'; // reabre para que puedan volver a subir
                $sustentoActual->save();
            }

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $revisor->id,
                'actor_tipo' => $revisor->hasRole('rrhh') ? 'rrhh' : 'jefe_inmediato',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => $comentario ?? "Sustento {$resultado}.",
            ]);

            return $actual;
        });

        if ($resultado === 'observado') {
            $this->notificar->sustentoObservado($papeleta);
        }

        return $papeleta;
    }
}
