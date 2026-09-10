<?php

namespace App\Actions\Papeleta;

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
        $papeleta = $sustento->papeleta;

        if (! $papeleta->estado->equals(RetornoPendienteSustento::class)) {
            throw new PapeletaException('Esta papeleta ya no está esperando sustento.');
        }

        if ($sustento->estado !== 'presentado') {
            throw new PapeletaException('Este sustento todavía no tiene un archivo presentado para revisar.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $sustento, $revisor, $resultado, $comentario) {
            $sustento->estado = $resultado;
            $sustento->revisado_por_id = $revisor->id;
            $sustento->revisado_at = now();
            $sustento->save();

            $estadoAnterior = class_basename($papeleta->estado);

            // Aprobado -> cierra. Observado -> el sustento sigue pendiente
            // de un nuevo archivo, la papeleta se queda en el mismo estado
            // hasta que venza (job de vencimiento) o el trabajador vuelva
            // a presentar antes de la fecha_limite.
            if ($resultado === 'aprobado') {
                $papeleta->estado = new Cerrada($papeleta);
                $papeleta->save();
            } else {
                $sustento->estado = 'pendiente'; // reabre para que puedan volver a subir
                $sustento->save();
            }

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $revisor->id,
                'actor_tipo' => $revisor->hasRole('rrhh') ? 'rrhh' : 'jefe_inmediato',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $comentario ?? "Sustento {$resultado}.",
            ]);

            return $papeleta;
        });

        if ($resultado === 'observado') {
            $this->notificar->sustentoObservado($papeleta);
        }

        return $papeleta;
    }
}
