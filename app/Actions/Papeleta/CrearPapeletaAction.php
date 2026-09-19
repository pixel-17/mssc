<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
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
 *   `turnos` sigue existiendo para 728 y por defecto es solo
 *   informativo. Excepción: si el interruptor global MODO_ESTRICTO_728
 *   (tabla configuraciones, solo lo cambia Admin) está en "1", un
 *   trabajador 728 sin turno vigente (sin fila para hoy, o con
 *   es_descanso) NO puede crear papeleta — salvo bypass de aprobación
 *   (Emergencia), que nunca se bloquea por turno.
 * - Máximo 1 papeleta activa por carril (participa_regla_exclusividad),
 *   forzado también a nivel de BD (slot_normal_activo / slot_emergencia_activo).
 * - Sede/regimen/dia_operativo quedan fijados como fotografía inmutable.
 */
class CrearPapeletaAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(User $trabajador, Motivo $motivo, array $datos): Papeleta
    {
        if (! $motivo->activo) {
            throw new PapeletaException("El motivo {$motivo->nombre} ya no está disponible.");
        }

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
                    'hora_retorno_estimado' => $datos['hora_retorno_estimado'] ?? null,
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
     * 728 (rotativo): por defecto se busca el turno solo como referencia
     * (puede no existir, puede ser descanso) y NO bloquea ni valida la
     * hora. Si MODO_ESTRICTO_728 está activo, en cambio, la ausencia de
     * turno vigente sí bloquea (ver bloquearSiModoEstrictoSinTurno).
     * 276 (ordinario): ventana estricta contra el horario único global —
     * ya no contra una fila diaria en `turnos`, por eso no devuelve Turno.
     */
    private function resolverTurnoActivo(User $trabajador): ?Turno
    {
        if ($trabajador->regimen === '728') {
            // Turno::vigenteParaUsuario maneja el cruce de medianoche del
            // turno Noche, para que dia_operativo refleje el turno
            // correcto también entre 00:00 y 06:00.
            $turno = Turno::vigenteParaUsuario($trabajador->id);

            $this->bloquearSiModoEstrictoSinTurno($turno);

            return $turno;
        }

        if (! app(HorarioOrdinarioService::class)->estaDentroDeVentana()) {
            throw new PapeletaException(
                'Estás fuera de tu horario ordinario en este momento (o hoy no es día laborable). '.
                'No puedes crear una papeleta salvo un motivo que permita bypass de aprobación.'
            );
        }

        return null;
    }

    /**
     * MODO_ESTRICTO_728 (tabla configuraciones, clave global — no por
     * área ni por trabajador, solo Admin la cambia): si está en "1" y
     * el trabajador 728 no tiene turno vigente (sin fila para hoy, o
     * la fila vigente es un día de descanso), se bloquea la creación
     * con un mensaje editable en Configuraciones (MODO_ESTRICTO_728_MENSAJE)
     * en vez de un texto fijo en el código. Nunca aplica a régimen 276
     * (ese ya tiene su propia ventana en HorarioOrdinarioService) ni a
     * un motivo con bypass de aprobación (resolverTurnoActivo no llega
     * aquí en ese caso).
     */
    private function bloquearSiModoEstrictoSinTurno(?Turno $turno): void
    {
        $modoEstrictoActivo = Configuracion::valorDe('MODO_ESTRICTO_728', '0') === '1';

        if (! $modoEstrictoActivo) {
            return;
        }

        if ($turno && ! $turno->es_descanso) {
            return;
        }

        throw new PapeletaException(
            (string) Configuracion::valorDe(
                'MODO_ESTRICTO_728_MENSAJE',
                'No puedes crear una papeleta en este momento: no tienes un turno vigente asignado.'
            )
        );
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
