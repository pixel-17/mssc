<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\Turno;
use App\Models\User;
use App\Services\HorarioOrdinarioService;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Paso 1 del flujo: creación.
 *
 * - 276 (ordinario): ventana estricta contra el horario ÚNICO GLOBAL
 *   (HorarioOrdinarioService, editable en Configuraciones) — ya no
 *   contra una fila diaria por trabajador en `turnos` (insostenible con
 *   ~500 trabajadores 276). Fuera de ventana -> bloqueo total, salvo el
 *   motivo que tenga permite_bypass_aprobacion = true (Emergencia, por
 *   bandera, nunca por nombre — ver comentario en Motivo.php).
 * - 728 (rotativo): activo 24/7, sin validar horario ni día de descanso.
 *   `turnos` sigue existiendo para 728 pero es solo informativo.
 * - Máximo 1 papeleta activa por carril (participa_regla_exclusividad),
 *   forzado también a nivel de BD (slot_normal_activo / slot_emergencia_activo).
 * - Sede/regimen/dia_operativo quedan fijados como fotografía inmutable.
 */
class CrearPapeletaAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(User $trabajador, Motivo $motivo, array $datos): Papeleta
    {
        if ($motivo->adjunto === 'obligatorio' && empty($datos['justificacion'] ?? null)) {
            throw new PapeletaException("La justificación es obligatoria para el motivo {$motivo->nombre}.");
        }

        $bypassAprobacion = $motivo->permite_bypass_aprobacion;

        $turno = $bypassAprobacion ? null : $this->resolverTurnoActivo($trabajador);

        $unidad = $trabajador->unidadOrganica;

        try {
            $papeleta = DB::transaction(function () use ($trabajador, $motivo, $datos, $bypassAprobacion, $turno, $unidad) {
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
                    'visto_bueno_jefe_emergencia' => $bypassAprobacion ? 'pendiente' : null,
                    'visto_bueno_rrhh_emergencia' => $bypassAprobacion ? 'pendiente' : null,
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
        } catch (QueryException $e) {
            // Red de seguridad ante condición de carrera: dos requests
            // casi simultáneas pueden pasar el SELECT de
            // verificarExclusividad() antes de que cualquiera haga el
            // INSERT. El constraint único de BD (slot_normal_activo /
            // slot_emergencia_activo por trabajador) es quien realmente
            // lo impide; acá solo traducimos ese fallo al mismo mensaje
            // amigable en vez de dejar pasar un error crudo de BD.
            if ($e->getCode() === '23000') {
                $mensaje = $motivo->participa_regla_exclusividad
                    ? 'Ya tienes una papeleta activa (Particular, Salud o Comisión). Solo puedes tener una a la vez.'
                    : 'Ya tienes una papeleta de este carril (Emergencia) activa.';

                throw new PapeletaException($mensaje);
            }

            throw $e;
        }

        // Fuera de la transacción: si algo falla en el envío (push caído,
        // cola no disponible) nunca debe revertir la creación ya
        // confirmada en BD.
        $this->notificar->creada($papeleta);

        return $papeleta;
    }

    /**
     * 728 (rotativo): se busca el turno solo como referencia (puede no
     * existir, puede ser descanso) pero NUNCA bloquea ni se valida la hora.
     * 276 (ordinario): ventana estricta contra el horario único global —
     * ya no contra una fila diaria en `turnos`, por eso no devuelve Turno.
     */
    private function resolverTurnoActivo(User $trabajador): ?Turno
    {
        if ($trabajador->regimen === '728') {
            // Turno::vigenteParaUsuario maneja el cruce de medianoche del
            // turno Noche, para que dia_operativo refleje el turno
            // correcto también entre 00:00 y 06:00. Sigue siendo solo
            // informativo: puede ser null y no bloquea nada para 728.
            return Turno::vigenteParaUsuario($trabajador->id);
        }

        if (! app(HorarioOrdinarioService::class)->estaDentroDeVentana()) {
            throw new PapeletaException(
                'Estás fuera de tu horario ordinario en este momento (o hoy no es día laborable). '.
                'No puedes crear una papeleta salvo un motivo que permita bypass de aprobación.'
            );
        }

        return null;
    }

    private function verificarExclusividad(User $trabajador, Motivo $motivo): void
    {
        $columna = $motivo->participa_regla_exclusividad ? 'slot_normal_activo' : 'slot_emergencia_activo';

        // lockForUpdate: si ya existe una fila activa de este trabajador,
        // una segunda transacción concurrente espera a que esta termine
        // en vez de leer un estado ya obsoleto. No cubre el caso de la
        // PRIMERA papeleta (no hay fila que bloquear todavía) — para eso
        // está el catch de QueryException en ejecutar().
        $existeActiva = Papeleta::where('trabajador_id', $trabajador->id)
            ->where($columna, true)
            ->lockForUpdate()
            ->exists();

        if ($existeActiva) {
            $mensaje = $motivo->participa_regla_exclusividad
                ? 'Ya tienes una papeleta activa (Particular, Salud o Comisión). Solo puedes tener una a la vez.'
                : 'Ya tienes una papeleta de este carril (Emergencia) activa.';

            throw new PapeletaException($mensaje);
        }
    }
}
