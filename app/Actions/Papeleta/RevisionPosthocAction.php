<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Paso 4: toda papeleta que el jefe autorizó fuera del horario de RRHH
 * (autorizado_con_rrhh_fuera_horario = true) necesita evidencia
 * explícita de RRHH al reanudar labores — incluso si la papeleta ya
 * llegó a un estado terminal (CERRADA, etc.), por eso esta acción NO
 * toca `estado`, solo el carril paralelo revision_posthoc_*.
 *
 * "Observada" aquí no reabre el flujo de la papeleta (no hay a dónde
 * volver, la salida ya ocurrió): deja constancia de auditoría/control
 * de que el jefe autorizó algo que RRHH cuestiona.
 */
class RevisionPosthocAction
{
    use ExigeDecisorAjeno;

    public function aprobar(Papeleta $papeleta, User $rrhh): Papeleta
    {
        return $this->resolver($papeleta, $rrhh, 'aprobada');
    }

    public function observar(Papeleta $papeleta, User $rrhh, string $comentario): Papeleta
    {
        return $this->resolver($papeleta, $rrhh, 'observada', $comentario);
    }

    private function resolver(Papeleta $papeleta, User $rrhh, string $resultado, ?string $comentario = null): Papeleta
    {
        return DB::transaction(function () use ($papeleta, $rrhh, $resultado, $comentario) {
            // Relectura bajo lock: dos revisores de RRHH casi simultáneos ya
            // no pueden pasar ambos el chequeo de "sigue pendiente".
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->autorizado_con_rrhh_fuera_horario) {
                throw new PapeletaException('Esta papeleta no requiere revisión post-hoc de RRHH.');
            }

            if ($actual->revision_posthoc_estado !== 'pendiente') {
                throw new PapeletaException('Esta papeleta ya tiene una revisión post-hoc registrada.');
            }

            $actual->revision_posthoc_estado = $resultado;
            $actual->revision_posthoc_por_id = $rrhh->id;
            $actual->revision_posthoc_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => class_basename($actual->estado),
                'estado_nuevo' => class_basename($actual->estado), // no cambia: revisión post-hoc es un carril aparte
                'justificacion' => $comentario ?? 'Revisión post-hoc: autorización de jefe fuera de horario RRHH.',
            ]);

            return $actual;
        });
    }
}
