<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\ConfiguracionTurno;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\Turno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
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
 * - 728 (rotativo): activo 24/7, sin validar horario ni día de descanso.
 *   `turnos` sigue existiendo para 728 y por defecto es solo
 *   informativo. Excepción: si el interruptor global MODO_ESTRICTO_728
 *   (tabla configuraciones, solo lo cambia Admin) está en "1", un
 *   trabajador 728 sin turno vigente (sin fila para hoy, o con
 *   es_descanso) NO puede crear papeleta.
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
        [$jefeInmediatoId, $jefeAreaId] = $unidad ? $unidad->jefaturasDe($trabajador) : [null, null];

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
                    'estado_anterior' => class_basename($papeleta->estado),
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
                'No puedes crear una papeleta fuera de horario.'
            );
        }

        return null;
    }

    /**
     * Instante en que termina el turno de esta papeleta. 728: fin del
     * turno vigente (Turno::finReal resuelve el cruce de medianoche del
     * turno Noche); si no hay fila de turno cargada pero su ciclo
     * configurado es Noche y está dentro de esa ventana, el fin de esa
     * noche (finDeNocheSinTurnoCargado); en cualquier otro caso, el
     * cierre del día. 276: fin del horario ordinario de hoy.
     */
    private function resolverFinDeTurno(User $trabajador, ?Turno $turno): Carbon
    {
        if ($trabajador->regimen === '728') {
            return $turno?->finReal()
                ?? $this->finDeNocheSinTurnoCargado($trabajador)
                ?? now()->endOfDay();
        }

        return app(HorarioOrdinarioService::class)->finDelDia(now());
    }

    /**
     * Sin modo estricto, un 728 puede crear papeleta aunque nadie le haya
     * cargado el turno. Si su ciclo configurado (configuraciones_turno) es
     * Noche y la papeleta se crea dentro de la ventana nocturna
     * (22:00-06:00 por defecto), el turno termina al amanecer y no a las
     * 23:59: de lo contrario la papeleta vencería (o se marcaría abandono)
     * a medianoche, en pleno turno. Si hay alguna fila de `turnos` para
     * hoy o ayer (p. ej. un día de descanso) no se infiere nada: el
     * trabajador no está de turno.
     */
    private function finDeNocheSinTurnoCargado(User $trabajador): ?Carbon
    {
        $config = ConfiguracionTurno::where('user_id', $trabajador->id)->first();

        if ($config?->turno !== 'NOCHE') {
            return null;
        }

        $ahora = now();

        $hayFilaReciente = Turno::where('user_id', $trabajador->id)
            ->whereIn('fecha', [$ahora->toDateString(), $ahora->copy()->subDay()->toDateString()])
            ->exists();

        if ($hayFilaReciente) {
            return null;
        }

        [$horaInicio, $horaFin] = app(GeneradorTurnoMensualService::class)->horasDe('NOCHE');

        $inicioHoy = $ahora->copy()->setTimeFromTimeString($horaInicio);
        $finHoy = $ahora->copy()->setTimeFromTimeString($horaFin);

        // Solo tiene sentido si la noche cruza medianoche (inicio > fin).
        if (! $finHoy->lessThan($inicioHoy)) {
            return null;
        }

        // Madrugada: la noche empezó ayer y termina hoy.
        if ($ahora->lessThanOrEqualTo($finHoy)) {
            return $finHoy;
        }

        // Tarde-noche: la noche empezó hoy y termina mañana.
        if ($ahora->greaterThanOrEqualTo($inicioHoy)) {
            return $finHoy->addDay();
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
     * (ese ya tiene su propia ventana en HorarioOrdinarioService).
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
}
