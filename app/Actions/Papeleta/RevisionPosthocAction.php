<?php

namespace App\Actions\Papeleta;

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
        if (! $papeleta->autorizado_con_rrhh_fuera_horario) {
            throw new PapeletaException('Esta papeleta no requiere revisión post-hoc de RRHH.');
        }

        if ($papeleta->revision_posthoc_estado !== 'pendiente') {
            throw new PapeletaException('Esta papeleta ya tiene una revisión post-hoc registrada.');
        }

        return DB::transaction(function () use ($papeleta, $rrhh, $resultado, $comentario) {
            $papeleta->revision_posthoc_estado = $resultado;
            $papeleta->revision_posthoc_por_id = $rrhh->id;
            $papeleta->revision_posthoc_at = now();
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => class_basename($papeleta->estado),
                'estado_nuevo' => class_basename($papeleta->estado), // no cambia: revisión post-hoc es un carril aparte
                'justificacion' => $comentario ?? 'Revisión post-hoc: autorización de jefe fuera de horario RRHH.',
            ]);

            return $papeleta;
        });
    }
}
