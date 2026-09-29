<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use Illuminate\Support\Facades\DB;

/**
 * Paso 4: toda papeleta que el jefe autorizó fuera del horario de RRHH
 * (autorizado_con_rrhh_fuera_horario = true) necesita evidencia
 * explícita de RRHH al reanudar labores — incluso si la papeleta ya
 * llegó a un estado terminal (CERRADA, etc.), por eso esta acción NO
 * toca `estado`, solo el carril paralelo revision_posthoc_*.
 *
 * Observar aquí no reabre la papeleta (la salida ya ocurrió), pero ya
 * no es un final mudo: la observación vuelve al MISMO jefe que
 * autorizó (ver ResponderPosthocAction), que responde por escrito y,
 * si quiere, con un adjunto; entonces la revisión pasa a 'respondida'
 * y RRHH decide de nuevo. Mismo tope que la observación previa de RRHH
 * (TOPE_OBSERVACIONES_RRHH), con contador propio: al alcanzarlo, la
 * revisión queda 'observada_firme' (reparo definitivo de auditoría).
 * También queda firme si no hubo jefe que autorizara (autorización de
 * sistema): no hay quien responda.
 */
class RevisionPosthocAction
{
    use ExigeDecisorAjeno;

    /** Estados desde los que RRHH puede revisar (primera vez o tras la respuesta del jefe). */
    private const REVISABLES = ['pendiente', 'respondida'];

    public function __construct(private NotificarPapeletaService $notificar) {}

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
        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $resultado, $comentario) {
            // Relectura bajo lock: dos revisores de RRHH casi simultáneos ya
            // no pueden pasar ambos el chequeo de "sigue pendiente".
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $rrhh);

            if (! $actual->autorizado_con_rrhh_fuera_horario) {
                throw new PapeletaException('Esta papeleta no requiere revisión post-hoc de RRHH.');
            }

            if (! in_array($actual->revision_posthoc_estado, self::REVISABLES, true)) {
                throw new PapeletaException('Esta papeleta ya tiene una revisión post-hoc registrada.');
            }

            $estadoNuevo = $resultado;

            if ($resultado === 'observada') {
                $tope = (int) Configuracion::valorDe('TOPE_OBSERVACIONES_RRHH', 3);
                $actual->contador_observaciones_posthoc++;
                $actual->posthoc_observacion = $comentario;

                $sinQuienResponda = $actual->resuelto_por_jefe_id === null;

                if ($sinQuienResponda || $actual->contador_observaciones_posthoc >= $tope) {
                    $estadoNuevo = 'observada_firme';
                }
            }

            $actual->revision_posthoc_estado = $estadoNuevo;
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

        if (in_array($papeleta->revision_posthoc_estado, ['observada', 'observada_firme'], true)) {
            $this->notificar->posthocObservada($papeleta);
        }

        return $papeleta;
    }
}
