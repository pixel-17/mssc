<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
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
 *
 * Si RRHH NO aprueba (noAprobar), o la observación queda firme, el post-hoc
 * no se queda en el aire: la papeleta pasa a Particular (con descuento por
 * las horas que estuvo fuera). Lo que ya venía como Particular termina
 * Particular. Si el trabajador sigue fuera, el motivo cambia ya y al marcar
 * su retorno se cierra como Finalizada (ver MarcarRetornoAction).
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

    /** RRHH no aprueba (o la justificación no es válida): la papeleta pasa a Particular, con descuento. */
    public function noAprobar(Papeleta $papeleta, User $rrhh, string $comentario): Papeleta
    {
        return $this->resolver($papeleta, $rrhh, 'no_aprobada', $comentario);
    }

    private function resolver(Papeleta $papeleta, User $rrhh, string $resultado, ?string $comentario = null): Papeleta
    {
        $reclasificada = false;

        $papeleta = DB::transaction(function () use ($papeleta, $rrhh, $resultado, $comentario, &$reclasificada) {
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

            $estadoAntes = class_basename($actual->estado);
            $motivoAnteriorId = $actual->motivo_id;
            $reclasificada = false;

            // Sin decisión a favor no queda en el aire: pasa a Particular.
            if (in_array($estadoNuevo, ['no_aprobada', 'observada_firme'], true)) {
                $reclasificada = $this->pasarAParticular($actual);
            }

            $actual->revision_posthoc_estado = $estadoNuevo;
            $actual->revision_posthoc_por_id = $rrhh->id;
            $actual->revision_posthoc_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $rrhh->id,
                'actor_tipo' => 'rrhh',
                'estado_anterior' => $estadoAntes,
                // Solo cambia si la reclasificación cerró la papeleta con descuento;
                // en el resto de casos la revisión post-hoc es un carril aparte.
                'estado_nuevo' => class_basename($actual->estado),
                'motivo_anterior_id' => $reclasificada ? $motivoAnteriorId : null,
                'motivo_nuevo_id' => $reclasificada ? $actual->motivo_id : null,
                'justificacion' => $comentario ?? 'Revisión post-hoc: autorización de jefe fuera de horario RRHH.',
            ]);

            return $actual;
        });

        if (in_array($papeleta->revision_posthoc_estado, ['observada', 'observada_firme'], true)) {
            $this->notificar->posthocObservada($papeleta);
        }

        if ($reclasificada) {
            $this->notificar->posthocAParticular($papeleta);
        }

        return $papeleta;
    }

    /**
     * Pasa la papeleta a Particular (descuenta las horas que estuvo fuera).
     * Devuelve true si cambió algo; si ya era un motivo que descuenta, no
     * hay nada que cambiar y devuelve false. Debe llamarse dentro de la
     * transacción con la fila ya bloqueada.
     */
    private function pasarAParticular(Papeleta $actual): bool
    {
        if ($actual->motivo->suma_descuento) {
            return false;
        }

        $particular = Motivo::where('es_destino_reclasificacion', true)->first();

        if (! $particular) {
            throw new PapeletaException('No hay un motivo configurado como destino de reclasificación (es_destino_reclasificacion).');
        }

        $actual->motivo_original_id = $actual->motivo_id;
        $actual->motivo_id = $particular->id;
        $actual->requiere_visto_bueno = false;

        if ($actual->estado->equals(Cerrada::class)) {
            $actual->transicionarA(Finalizada::class);
        } elseif ($actual->estado->equals(EnJustificacion::class)) {
            // Ya no hay nada que justificar: se cierra el sustento abierto y termina con descuento.
            $actual->sustentos()
                ->whereIn('estado', ['pendiente', 'presentado', 'observado'])
                ->update(['estado' => 'vencido']);

            $actual->transicionarA(Finalizada::class);
        }

        // AutorizadaYCorriendo: sigue fuera; solo cambia el motivo y, al marcar
        // el retorno, un motivo que descuenta lo cierra como Finalizada.

        return true;
    }
}
