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
 * - Jefe inmediato (o, si quien crea la papeleta es él mismo jefe
 *   inmediato, su Jefe de Área) sin resolver o inactivo: bloqueo total
 *   SIEMPRE, sin excepción por régimen ni por posición en el
 *   organigrama — es un hueco de configuración (turno sin jefe
 *   asignado, jefe dado de baja, o tope del organigrama sin nadie
 *   arriba), no una indisponibilidad temporal. La papeleta nunca se
 *   crea esperando a alguien que no existe.
 * - Trabajador raso (no es jefe de nadie): SIEMPRE PENDIENTE_JEFE, sin
 *   importar si su jefe inmediato está "disponible ahora" (fuera de
 *   horario ordinario, feriado, etc. — ver DecisorDisponibleService).
 *   Nunca salta a RRHH y el sistema nunca la autoriza por sí solo: si
 *   el jefe no decide a tiempo, la papeleta simplemente vence (ver
 *   ProcesarVencimientosPapeletas), nunca escala a nadie más.
 * - Jefe inmediato enviando SU PROPIA papeleta (sube un nivel, a su
 *   Jefe de Área): si su Jefe de Área no está disponible ahora mismo,
 *   sí escala:
 *     - Si RRHH está en horario -> PENDIENTE_RRHH directo.
 *     - Si RRHH también está fuera de horario -> el sistema autoriza
 *       (AUTORIZADA_Y_CORRIENDO) y queda para revisión post-hoc
 *       obligatoria al día siguiente (mismo mecanismo que AprobarJefeAction
 *       usa cuando el jefe aprueba con RRHH fuera de horario). Es el
 *       ÚNICO caso en que el sistema autoriza; queda registrado como
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
        // TODOS los candidatos a jefe inmediato de ESTE turno vía
        // jefes_turno — ver UnidadOrganica::resolverJefesInmediatos().
        // Para 276 (turno null) esto sigue siendo, como mucho, un único
        // candidato: jefe_id de la unidad.
        [$jefesInmediatosIds, $jefeAreaId] = $unidad ? $unidad->jefaturasMultiplesDe($trabajador, $turno?->codigo()) : [[], null];

        // resolverJefesInmediatos() ya filtra a jefes activos, así que
        // estos son directamente los candidatos válidos (0 o más).
        $jefesInmediatos = $jefesInmediatosIds !== [] ? User::whereKey($jefesInmediatosIds)->get() : collect();

        // ¿Quien crea la papeleta es, él mismo, jefe inmediato de su
        // unidad? Si lo es, jefaturasMultiplesDe() ya resolvió más
        // arriba en el organigrama (su Jefe de Área) en $jefesInmediatos.
        // Es el único rol cuya papeleta puede escalar a RRHH o a
        // autorización del sistema (ver $estadoInicial más abajo): un
        // trabajador raso nunca escala, solo espera a su jefe inmediato.
        $esJefeInmediatoDeLaUnidad = $unidad?->esJefeDeLaUnidad($trabajador) ?? false;

        // Regla de estructura: el jefe inmediato (o, si quien crea la
        // papeleta es él mismo jefe inmediato, su Jefe de Área) SIEMPRE
        // debe existir y estar activo — sin excepción por régimen ni por
        // posición en el organigrama. Sin él, no se puede crear la
        // papeleta: bloqueo total, nunca se queda esperando a alguien
        // que no existe ni escala a RRHH por defecto.
        if ($jefesInmediatos->isEmpty()) {
            throw new PapeletaException(
                $esJefeInmediatoDeLaUnidad
                    ? 'Tu unidad no tiene un Jefe de Área activo asignado. Comunícate con Administración para regularizar la jefatura antes de crear una papeleta.'
                    : 'Tu turno actual no tiene un jefe inmediato activo asignado. Comunícate con tu Jefe de Área para regularizar la jefatura antes de crear una papeleta.'
            );
        }

        // Disponibilidad real: estar asignado en la BD no implica poder
        // decidir ahora (ver DecisorDisponibleService). Con varios
        // candidatos basta con que UNO esté disponible para no escalar —
        // en 728 esto siempre es cierto en cuanto hay al menos un
        // candidato, porque DecisorDisponibleService considera a
        // cualquier decisor 728 siempre disponible. Solo importa para
        // decidir si se escala, y solo se escala cuando
        // $esJefeInmediatoDeLaUnidad es true (ver $estadoInicial).
        $jefeDisponible = $jefesInmediatos->contains(
            fn (User $jefe) => $this->decisorDisponible->estaDisponibleAhora($jefe)
        );

        // Columna única (compatibilidad con reportes/dashboards): se
        // fotografía preferentemente a un candidato disponible, o al
        // primero si ninguno lo está. La lista completa de candidatos se
        // guarda aparte, ver Papeleta::jefesCandidatos().
        $jefeInmediatoId = $jefesInmediatos
            ->first(fn (User $jefe) => $this->decisorDisponible->estaDisponibleAhora($jefe))
            ?->id ?? $jefesInmediatos->first()?->id;

        $rrhhEnHorario = $this->horarioRrhh->estaEnHorarioAhora();

        // Trabajador raso (no es jefe de nadie): SIEMPRE PendienteJefe,
        // sin importar si el jefe está "disponible ahora mismo" — nunca
        // salta a RRHH ni el sistema la autoriza por sí solo. Si el jefe
        // no decide a tiempo, la papeleta simplemente vence (ver
        // ProcesarVencimientosPapeletas), nunca escala a nadie más.
        // Jefe inmediato enviando SU PROPIA papeleta: aquí sí escala a
        // RRHH y, si RRHH también está fuera de horario, el sistema
        // autoriza con revisión post-hoc — es el ÚNICO caso en que el
        // sistema autoriza.
        $estadoInicial = match (true) {
            ! $esJefeInmediatoDeLaUnidad => PendienteJefe::class,
            $jefeDisponible => PendienteJefe::class,
            $rrhhEnHorario => PendienteRrhh::class,
            default => AutorizadaYCorriendo::class,
        };

        $autorizaSistema = $estadoInicial === AutorizadaYCorriendo::class;

        $papeleta = DB::transaction(function () use ($trabajador, $motivo, $datos, $turno, $finTurno, $jefeInmediatoId, $jefesInmediatosIds, $jefeAreaId, $jefeDisponible, $esJefeInmediatoDeLaUnidad, $estadoInicial, $autorizaSistema) {
            $campos = [
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
            ];

            // No se manda 'no_aplica' a mano: se deja que la BD aplique
            // su propio default (columna no nullable) y así ningún otro
            // punto de creación de Papeleta puede volver a colar un
            // null aquí por accidente. Solo el caso que de verdad
            // necesita otro valor lo pisa explícitamente.
            if ($autorizaSistema) {
                $campos['revision_posthoc_estado'] = 'pendiente';
            }

            $papeleta = Papeleta::create($campos);

            // Fotografía de TODOS los candidatos resueltos (no solo el
            // guardado en jefe_inmediato_id): cualquiera puede decidir
            // esta papeleta, el que actúe primero — ver
            // Papeleta::scopeDeJefeInmediato().
            if ($jefesInmediatosIds !== []) {
                $papeleta->jefesCandidatos()->createMany(
                    collect($jefesInmediatosIds)->map(fn (int $id) => ['user_id' => $id])->all()
                );
            }

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $trabajador->id,
                'actor_tipo' => 'trabajador',
                'estado_nuevo' => class_basename($papeleta->estado),
                'motivo_nuevo_id' => $motivo->id,
                'justificacion' => $datos['justificacion'] ?? null,
            ]);

            if ($esJefeInmediatoDeLaUnidad && ! $jefeDisponible) {
                // Este historial explicativo solo aplica al caso que sí
                // puede escalar (jefe inmediato enviando su propia
                // papeleta). Un trabajador raso siempre queda en
                // PendienteJefe y no necesita esta explicación.
                //
                // Única fuente de verdad: se le pregunta a la papeleta ya
                // creada, sobre las columnas fotografiadas — el mismo
                // método que usan ObservarRrhhAction y el resto del
                // sistema. Ya no se recalcula la regla del tope del
                // organigrama por segunda vez aquí.
                $sinJefatura = $papeleta->sinJefatura();

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
