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
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paso 1 del flujo: creación.
 *
 * - 276 (ordinario): ventana estricta contra el horario ÚNICO GLOBAL
 *   (HorarioOrdinarioService, editable en Configuraciones) — ya no
 *   contra una fila diaria por trabajador en `turnos` (insostenible con
 *   ~500 trabajadores 276). Fuera de ventana -> bloqueo total.
 * - 728 (rotativo): 24/7, sin validar horario. Sí exige turno vigente
 *   cargado en `turnos` (fila de hoy, y que no sea es_descanso) para
 *   poder crear la papeleta: fuera de eso, bloqueo total. Ya no es un
 *   interruptor que el admin pueda activar/desactivar (antes
 *   MODO_ESTRICTO_728): es la regla fija, siempre activa.
 * - Un trabajador puede tener varias papeletas al mismo tiempo: no hay
 *   regla de exclusividad ni columnas "slot" a nivel de BD.
 * - Decisor resuelto pero NO disponible ahora mismo (DecisorDisponibleService:
 *   jefe 276 fuera de su horario ordinario, o feriado) o sin jefatura
 *   (tope del organigrama sin nadie arriba): la papeleta NO se queda
 *   esperando a un decisor inalcanzable. Sube un nivel:
 *     - Si RRHH está en horario -> PENDIENTE_RRHH directo (salta al jefe).
 *     - Si RRHH también está fuera de horario -> el sistema autoriza
 *       (AUTORIZADA_Y_CORRIENDO) y queda para revisión post-hoc
 *       obligatoria al día siguiente (mismo mecanismo que AprobarJefeAction
 *       usa cuando el jefe aprueba con RRHH fuera de horario). Nunca es el
 *       propio trabajador quien se autoautoriza: queda registrado como
 *       actor_tipo = 'sistema', nadie "decidió" nada.
 * - Sede/regimen/dia_operativo/fin_turno_at quedan fijados como fotografía
 *   inmutable. fin_turno_at es el instante real en que termina el turno
 *   (728, con cruce de medianoche) o el horario ordinario (276): los jobs
 *   de vencimiento y de abandono comparan contra él.
 */
class CrearPapeletaAction
{
    public function __construct(
        private NotificarPapeletaService $notificar,
        private DecisorDisponibleService $decisorDisponible,
        private RrhhHorarioService $horarioRrhh,
    ) {}

    public function ejecutar(User $trabajador, Motivo $motivo, array $datos): Papeleta
    {
        if (! $motivo->activo) {
            throw new PapeletaException("El motivo {$motivo->nombre} ya no está disponible.");
        }

        if ($motivo->adjunto === 'obligatorio' && empty($datos['justificacion'] ?? null)) {
            throw new PapeletaException("La justificación es obligatoria para el motivo {$motivo->nombre}.");
        }

        $turno = $this->resolverTurnoActivo($trabajador);

        $finTurno = $this->resolverFinDeTurno($trabajador, $turno);

        $unidad = $trabajador->unidadOrganica;

        // Misma regla que UserObserver: quien encabeza su unidad NO es su propio jefe.
        // El código de turno (MANANA/TARDE/NOCHE, null para 276) resuelve
        // el jefe inmediato de ESTE turno vía jefes_turno — ver
        // UnidadOrganica::resolverJefeInmediato().
        [$jefeInmediatoId, $jefeAreaId] = $unidad ? $unidad->jefaturasDe($trabajador, $turno?->codigo()) : [null, null];

        // Tope del organigrama: encabeza su unidad y no tiene a nadie arriba.
        $sinJefatura = $unidad !== null
            && (int) $unidad->jefe_id === (int) $trabajador->id
            && $jefeInmediatoId === null
            && $jefeAreaId === null
            && ! $trabajador->jefesInmediatosAdicionales()->exists();

        // Disponibilidad real del decisor resuelto (null si sinJefatura,
        // o si por alguna razón el jefe_inmediato_id no resuelve a un User
        // activo): estar asignado en la BD no implica poder decidir ahora.
        $jefeInmediato = $jefeInmediatoId ? User::find($jefeInmediatoId) : null;
        $jefeDisponible = $this->decisorDisponible->estaDisponibleAhora($jefeInmediato);
        $rrhhEnHorario = $this->horarioRrhh->estaEnHorarioAhora();

        $estadoInicial = match (true) {
            $jefeDisponible => PendienteJefe::class,
            $rrhhEnHorario => PendienteRrhh::class, // jefe no disponible (o sin jefatura): salta directo a RRHH
            default => AutorizadaYCorriendo::class, // nadie disponible: autoriza el sistema, no una persona
        };

        $autorizaSistema = $estadoInicial === AutorizadaYCorriendo::class;

        $papeleta = DB::transaction(function () use ($trabajador, $motivo, $datos, $turno, $finTurno, $jefeInmediatoId, $jefeAreaId, $sinJefatura, $jefeDisponible, $estadoInicial, $autorizaSistema) {
            $papeleta = Papeleta::create([
                'trabajador_id' => $trabajador->id,
                'motivo_id' => $motivo->id,
                'sede_id' => $trabajador->sede_id,
                'regimen' => $trabajador->regimen,
                'dia_operativo' => $turno?->fecha ?? now()->toDateString(),
                'fin_turno_at' => $finTurno,
                'estado' => $estadoInicial,
                'jefe_inmediato_id' => $jefeInmediatoId,
                'jefe_area_id' => $jefeAreaId,
                'hora_retorno_estimado' => $datos['hora_retorno_estimado'] ?? null,
                'justificacion' => $datos['justificacion'] ?? null,
                'adjunto_inicial_path' => $datos['adjunto_inicial_path'] ?? null,
                'autorizado_con_rrhh_fuera_horario' => $autorizaSistema,
                'hora_salida_real' => $autorizaSistema ? now() : null,
                'revision_posthoc_estado' => $autorizaSistema ? 'pendiente' : null,
            ]);

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $trabajador->id,
                'actor_tipo' => 'trabajador',
                'estado_nuevo' => class_basename($papeleta->estado),
                'motivo_nuevo_id' => $motivo->id,
                'justificacion' => $datos['justificacion'] ?? null,
            ]);

            if (! $jefeDisponible) {
                HistorialPapeleta::create([
                    'papeleta_id' => $papeleta->id,
                    'actor_id' => null,
                    'actor_tipo' => 'sistema',
                    'estado_nuevo' => class_basename($papeleta->estado),
                    'justificacion' => $sinJefatura
                        ? ($autorizaSistema
                            ? 'Sin jefe superior en el organigrama y RRHH fuera de horario: autorización automática, pendiente de revisión post-hoc.'
                            : 'Sin jefe superior en el organigrama: la papeleta se envía directo a RRHH.')
                        : ($autorizaSistema
                            ? 'Jefe inmediato fuera de su horario y RRHH fuera de horario: autorización automática, pendiente de revisión post-hoc.'
                            : 'Jefe inmediato fuera de su horario: la papeleta se envía directo a RRHH.'),
                ]);
            }

            return $papeleta;
        });

        // Fuera de la transacción: si algo falla en el envío (push caído,
        // cola no disponible) nunca debe revertir la creación ya
        // confirmada en BD.
        match (true) {
            $autorizaSistema => (function () use ($papeleta) {
                $this->notificar->puedeSalir($papeleta);
                $this->notificar->revisionPosthocPendiente($papeleta);
            })(),
            $estadoInicial === PendienteRrhh::class => $this->notificar->pendienteDeRrhh($papeleta),
            default => $this->notificar->creada($papeleta),
        };

        return $papeleta;
    }

    /**
     * 728 (rotativo): SIEMPRE debe tener un turno vigente cargado para
     * crear una papeleta (sin fila para hoy, o con es_descanso -> se
     * bloquea). Ya no es un interruptor que el admin pueda activar o
     * desactivar (antes MODO_ESTRICTO_728): es la única regla, fija.
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

            if (! $turno || $turno->es_descanso) {
                throw new PapeletaException(
                    'No puedes crear una papeleta en este momento: no tienes un turno vigente asignado. '.
                    'Comunícate con tu jefe para que regularice tu turno.'
                );
            }

            return $turno;
        }

        if (! app(HorarioOrdinarioService::class)->estaDentroDeVentana()) {
            throw new PapeletaException(
                'Estás fuera de tu horario ordinario en este momento (o hoy no es día laborable). '.
                'No puedes crear una papeleta fuera de horario.'
            );
        }

        return null;
    }

    /**
     * Instante en que termina el turno de esta papeleta. 728: fin del
     * turno vigente, ya garantizado no-null por resolverTurnoActivo
     * (Turno::finReal resuelve el cruce de medianoche del turno
     * Noche). 276: fin del horario ordinario de hoy.
     */
    private function resolverFinDeTurno(User $trabajador, ?Turno $turno): Carbon
    {
        if ($trabajador->regimen === '728') {
            return $turno->finReal() ?? now()->endOfDay();
        }

        return app(HorarioOrdinarioService::class)->finDelDia(now());
    }
}
