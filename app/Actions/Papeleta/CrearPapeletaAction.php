<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\Turno;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;

/**
 * Paso 1 del flujo: creación.
 *
 * - CAS: ventana estricta (NOW() dentro del turno de hoy). Sin turno ese
 *   día (descanso o sin cargar) -> bloqueo total, salvo el motivo que
 *   tenga permite_bypass_aprobacion = true (Emergencia, por bandera,
 *   nunca por nombre — ver comentario en Motivo.php).
 * - 728: activo 24/7, sin validar horario ni día de descanso.
 * - Máximo 1 papeleta activa por carril (participa_regla_exclusividad),
 *   forzado también a nivel de BD (slot_normal_activo / slot_emergencia_activo).
 * - Sede/regimen/dia_operativo quedan fijados como fotografía inmutable.
 */
class CrearPapeletaAction
{
    public function ejecutar(User $trabajador, Motivo $motivo, array $datos): Papeleta
    {
        if ($motivo->adjunto === 'obligatorio' && empty($datos['justificacion'] ?? null)) {
            throw new PapeletaException("La justificación es obligatoria para el motivo {$motivo->nombre}.");
        }

        $bypassAprobacion = $motivo->permite_bypass_aprobacion;

        $turno = $bypassAprobacion ? null : $this->resolverTurnoActivo($trabajador);

        $unidad = $trabajador->unidadOrganica;

        return DB::transaction(function () use ($trabajador, $motivo, $datos, $bypassAprobacion, $turno, $unidad) {
            $this->verificarExclusividad($trabajador, $motivo);

            $papeleta = Papeleta::create([
                'trabajador_id' => $trabajador->id,
                'motivo_id' => $motivo->id,
                'sede_id' => $trabajador->sede_id,
                'regimen' => $trabajador->regimen,
                'dia_operativo' => $turno?->fecha ?? now()->toDateString(),
                'estado' => $bypassAprobacion ? AutorizadaYCorriendo::class : PendienteJefe::class,
                'es_emergencia' => $bypassAprobacion,
                'slot_normal_activo' => $motivo->participa_regla_exclusividad ? true : null,
                'slot_emergencia_activo' => $motivo->participa_regla_exclusividad ? null : true,
                'jefe_inmediato_id' => $unidad?->jefeInmediato()?->id,
                'jefe_area_id' => $unidad?->jefeArea()?->id,
                'hora_salida_real' => $bypassAprobacion ? now() : null,
                'justificacion' => $datos['justificacion'] ?? null,
                'adjunto_inicial_path' => $datos['adjunto_inicial_path'] ?? null,
            ]);

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $trabajador->id,
                'actor_tipo' => 'trabajador',
                'estado_nuevo' => class_basename($papeleta->estado),
                'motivo_nuevo_id' => $motivo->id,
                'justificacion' => $datos['justificacion'] ?? null,
            ]);

            return $papeleta;
        });
    }

    /**
     * CAS: ventana estricta contra la fila de turno de hoy.
     * 728: se busca el turno solo como referencia (puede no existir,
     * puede ser descanso) pero NUNCA bloquea ni se valida la hora.
     */
    private function resolverTurnoActivo(User $trabajador): ?Turno
    {
        if ($trabajador->regimen === '728') {
            return Turno::where('user_id', $trabajador->id)
                ->whereDate('fecha', now()->toDateString())
                ->first(); // puede ser null, o incluso es_descanso=true: no importa para 728
        }

        // CAS: ventana estricta
        $turno = Turno::where('user_id', $trabajador->id)
            ->whereDate('fecha', now()->toDateString())
            ->where('es_descanso', false)
            ->whereTime('hora_inicio', '<=', now())
            ->whereTime('hora_fin', '>=', now())
            ->first();

        if (! $turno) {
            throw new PapeletaException(
                'No tienes un turno activo en este momento. Sin turno asignado (o en descanso), '.
                'no puedes crear una papeleta salvo un motivo que permita bypass de aprobación.'
            );
        }

        return $turno;
    }

    private function verificarExclusividad(User $trabajador, Motivo $motivo): void
    {
        $columna = $motivo->participa_regla_exclusividad ? 'slot_normal_activo' : 'slot_emergencia_activo';

        $existeActiva = Papeleta::where('trabajador_id', $trabajador->id)
            ->where($columna, true)
            ->exists();

        if ($existeActiva) {
            $mensaje = $motivo->participa_regla_exclusividad
                ? 'Ya tienes una papeleta activa (Particular, Salud o Comisión). Solo puedes tener una a la vez.'
                : 'Ya tienes una papeleta de este carril (Emergencia) activa.';

            throw new PapeletaException($mensaje);
        }
    }
}
