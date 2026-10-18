<?php

declare(strict_types=1);

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
 * - Un trabajador solo puede tener UNA papeleta viva a la vez (pendiente,
 *   observada, o autorizada y en curso; ver Papeleta::ESTADOS_VIVOS). Para crear
 *   otra primero debe cancelar la anterior (mientras espera decisión) o
 *   finalizarla (marcando su retorno). Se valida al inicio y se revalida
 *   dentro de la transacción, bajo lock de la fila del trabajador, para que dos
 *   envíos simultáneos no creen dos. No hay columnas "slot" a nivel de BD.
 * - Jefe inmediato (o, si quien crea la papeleta es él mismo jefe
 *   inmediato, su Jefe de Área) sin resolver o inactivo: bloqueo total
 *   SIEMPRE, sin excepción por régimen ni por posición en el
 *   organigrama — es un hueco de configuración (unidad sin jefe
 *   asignada, jefe dado de baja, o tope del organigrama sin nadie
 *   arriba), no una indisponibilidad temporal. La papeleta nunca se
 *   crea esperando a alguien que no existe.
 * - Jefe de ÁREA (encabeza una unidad con sub-unidades, ver
 *   User::esJefeDeArea): su papeleta NO sube a nadie; va SIEMPRE directo a
 *   PENDIENTE_RRHH, haya o no alguien arriba en el organigrama. RRHH solo
 *   puede aprobarla o rechazarla (sin jefatura no hay quien responda una
 *   observación). Exige al menos un usuario RRHH activo.
 * - Trabajador raso (no es jefe de nadie): SIEMPRE PENDIENTE_JEFE, sin
 *   importar si su jefe inmediato está "disponible ahora" (fuera de
 *   horario ordinario, etc. — ver DecisorDisponibleService).
 *   Nunca salta a RRHH y el sistema nunca la autoriza por sí solo: si
 *   el jefe no decide a tiempo, la papeleta simplemente vence (ver
 *   ProcesarVencimientosPapeletas), nunca escala a nadie más.
 * - Jefe inmediato enviando SU PROPIA papeleta (sube un nivel, a su
 *   Jefe de Área): SIEMPRE queda en PENDIENTE_JEFE esperando a su
 *   Jefe de Área, sin importar si está disponible ahora mismo o no.
 *   Si no existe Jefe de Área activo, la papeleta no se crea (bloqueo
 *   total, igual que cualquier otro trabajador sin jefe asignado).
 *   Nunca escala a RRHH ni el sistema la autoriza por sí solo.
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
        $this->validarMotivoYRegimen($trabajador, $motivo, $datos);
        $this->exigirSinPapeletaViva($trabajador);

        $turno = $this->resolverTurnoActivo($trabajador);
        $finTurno = $this->resolverFinDeTurno($trabajador, $turno);

        $jefatura = $this->resolverJefatura($trabajador);

        $estadoInicial = $this->determinarEstadoInicial($jefatura);
        $autorizaSistema = $estadoInicial === AutorizadaYCorriendo::class;

        $papeleta = DB::transaction(fn () => $this->crearPapeletaConHistorial(
            $trabajador,
            $motivo,
            $datos,
            $turno,
            $finTurno,
            $jefatura,
            $estadoInicial,
            $autorizaSistema,
        ));

        // Fuera de la transacción: si algo falla en el envío (push caído,
        // cola no disponible) nunca debe revertir la creación ya
        // confirmada en BD.
        $this->notificarCreacion($papeleta, $estadoInicial, $autorizaSistema);

        return $papeleta;
    }

    /**
     * Validaciones que no dependen de jefatura ni de turno: régimen
     * asignado, motivo vigente y justificación cuando el motivo la exige.
     */
    private function validarMotivoYRegimen(User $trabajador, Motivo $motivo, array $datos): void
    {
        // Sin régimen 276/728 no hay regla de horario que aplicar (antes caía
        // como 276 por descarte y fallaba en el INSERT: papeletas.regimen no
        // admite null). Se corta aquí con un mensaje que el usuario entienda.
        if (! in_array($trabajador->regimen, ['276', '728'], true)) {
            throw new PapeletaException(
                'Tu usuario no tiene un régimen (276 o 728) asignado. Comunícate con Administración para regularizarlo antes de crear una papeleta.'
            );
        }

        if (! $motivo->activo) {
            throw new PapeletaException("El motivo {$motivo->nombre} ya no está disponible.");
        }

        if ($motivo->adjunto === 'obligatorio' && empty($datos['justificacion'] ?? null)) {
            throw new PapeletaException("La justificación es obligatoria para el motivo {$motivo->nombre}.");
        }
    }

    /**
     * Resuelve a quién le corresponde decidir esta papeleta: directo a RRHH
     * (jefe de área), o jefe inmediato (y, si quien crea es él mismo jefe de
     * su unidad, su disponibilidad real para decidir ahora).
     *
     * @return array{directoARrhh: bool, jefeInmediatoId: ?int, jefeAreaId: ?int, jefeDisponible: bool, esJefeInmediatoDeLaUnidad: bool, rrhhEnHorario: bool}
     */
    private function resolverJefatura(User $trabajador): array
    {
        $unidad = $trabajador->unidadOrganica;

        // Un único jefe por unidad (`jefe_id`), sin importar régimen ni turno.
        // Misma regla que UserObserver: quien encabeza su unidad NO es su
        // propio jefe, su superior está un nivel arriba.
        // Jefe de área: no sube a nadie, su papeleta va directo a RRHH.
        $directoARrhh = $trabajador->esJefeDeArea();

        if ($directoARrhh && ! User::role('rrhh')->where('activo', true)->exists()) {
            throw new PapeletaException(
                'No hay personal de RR. HH. activo para recibir tu papeleta. Comunícate con Administración antes de crearla.'
            );
        }

        [$jefeInmediatoResuelto, $jefeAreaId] = (! $directoARrhh && $unidad)
            ? $unidad->jefaturasDe($trabajador)
            : [null, null];

        // Un jefe dado de baja cuenta como jefatura vacante.
        $jefeInmediato = $jefeInmediatoResuelto !== null
            ? User::whereKey($jefeInmediatoResuelto)->where('activo', true)->first()
            : null;

        // ¿Quien crea la papeleta es, él mismo, jefe de su unidad? Si lo es,
        // jefaturasDe() ya resolvió más arriba en el organigrama (su Jefe de
        // Área). Es el único rol cuya papeleta puede escalar a RRHH o a
        // autorización del sistema (ver determinarEstadoInicial): un
        // trabajador raso nunca escala, solo espera a su jefe inmediato.
        $esJefeInmediatoDeLaUnidad = $unidad?->esJefeDeLaUnidad($trabajador) ?? false;

        // Regla de estructura: el jefe inmediato (o, si quien crea es él
        // mismo jefe inmediato, su Jefe de Área) debe existir y estar activo.
        // Una jefatura vacante solo afecta a quien depende de ella: los
        // trabajadores propios del jefe de área y sus jefes inmediatos. Los
        // trabajadores de un jefe inmediato activo no se ven afectados.
        if (! $directoARrhh && ! $jefeInmediato) {
            if ($esJefeInmediatoDeLaUnidad) {
                throw new PapeletaException(
                    'Tu unidad no tiene un Jefe de Área activo asignado. Comunícate con Administración para regularizar la jefatura antes de crear una papeleta.'
                );
            }

            throw new PapeletaException(
                'Tu unidad no tiene un jefe activo asignado. Comunícate con Administración para regularizar la jefatura antes de crear una papeleta.'
            );
        }

        // Disponibilidad real: estar asignado en la BD no implica poder
        // decidir ahora (ver DecisorDisponibleService). Solo importa para
        // decidir si se escala, y solo escala quien es jefe de su unidad.
        $jefeDisponible = $jefeInmediato
            ? $this->decisorDisponible->estaDisponibleAhora($jefeInmediato)
            : false;

        return [
            'directoARrhh' => $directoARrhh,
            'jefeInmediatoId' => $jefeInmediato?->id,
            'jefeAreaId' => $jefeAreaId,
            'jefeDisponible' => $jefeDisponible,
            'esJefeInmediatoDeLaUnidad' => $esJefeInmediatoDeLaUnidad,
            'rrhhEnHorario' => $this->horarioRrhh->estaEnHorarioAhora(),
        ];
    }

    /**
     * @param  array{directoARrhh: bool, jefeDisponible: bool, esJefeInmediatoDeLaUnidad: bool, rrhhEnHorario: bool}  $jefatura
     * @return class-string<PendienteJefe|PendienteRrhh|AutorizadaYCorriendo>
     */
    private function determinarEstadoInicial(array $jefatura): string
    {
        // Jefe de área: va directo a RRHH; si RRHH está fuera de horario,
        // el sistema autoriza con revisión post-hoc obligatoria al día
        // siguiente.
        // Jefe inmediato enviando SU PROPIA papeleta: si su Jefe de Área no
        // puede decidir AHORA (descanso, de baja, etc.) escala a RRHH igual
        // que la papeleta del propio jefe de área — y si RRHH tampoco está
        // en horario, el sistema autoriza con revisión post-hoc, también
        // igual que esa ruta. Si su Jefe de Área SÍ puede decidir más tarde
        // (solo está fuera de horario, no de descanso), se queda esperándolo.
        // Trabajador raso: SIEMPRE PendienteJefe, esperando a su jefe
        // inmediato. Nunca escala a RRHH ni el sistema autoriza por sí
        // solo — si el decisor no actúa a tiempo, la papeleta vence (ver
        // ProcesarVencimientosPapeletas).
        return match (true) {
            $jefatura['directoARrhh'] && ! $jefatura['rrhhEnHorario'] => AutorizadaYCorriendo::class,
            $jefatura['directoARrhh'] => PendienteRrhh::class,
            $jefatura['esJefeInmediatoDeLaUnidad'] && ! $jefatura['jefeDisponible'] && ! $jefatura['rrhhEnHorario'] => AutorizadaYCorriendo::class,
            $jefatura['esJefeInmediatoDeLaUnidad'] && ! $jefatura['jefeDisponible'] => PendienteRrhh::class,
            default => PendienteJefe::class,
        };
    }

    /**
     * Crea la papeleta y su historial inicial. Debe correr dentro de
     * DB::transaction (ver ejecutar()): revalida bajo lock que el
     * trabajador sigue sin papeleta viva antes de insertar.
     *
     * @param  array{directoARrhh: bool, jefeInmediatoId: ?int, jefeAreaId: ?int, jefeDisponible: bool, esJefeInmediatoDeLaUnidad: bool}  $jefatura
     */
    private function crearPapeletaConHistorial(
        User $trabajador,
        Motivo $motivo,
        array $datos,
        ?Turno $turno,
        Carbon $finTurno,
        array $jefatura,
        string $estadoInicial,
        bool $autorizaSistema,
    ): Papeleta {
        // Revalidación bajo lock: la fila del trabajador sirve de candado, así dos
        // envíos casi simultáneos se atienden uno tras otro y el segundo ve al primero.
        User::whereKey($trabajador->id)->lockForUpdate()->first();
        $this->exigirSinPapeletaViva($trabajador);

        $campos = [
            'trabajador_id' => $trabajador->id,
            'motivo_id' => $motivo->id,
            'sede_id' => $trabajador->sede_id,
            'regimen' => $trabajador->regimen,
            'dia_operativo' => $turno?->fecha ?? now()->toDateString(),
            'fin_turno_at' => $finTurno,
            'estado' => $estadoInicial,
            'jefe_inmediato_id' => $jefatura['jefeInmediatoId'],
            'jefe_area_id' => $jefatura['jefeAreaId'],
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

        HistorialPapeleta::create([
            'papeleta_id' => $papeleta->id,
            'actor_id' => $trabajador->id,
            'actor_tipo' => 'trabajador',
            'estado_nuevo' => class_basename($papeleta->estado),
            'motivo_nuevo_id' => $motivo->id,
            'justificacion' => $datos['justificacion'] ?? null,
        ]);

        $this->registrarHistorialDeEscalamiento($papeleta, $jefatura, $autorizaSistema);

        return $papeleta;
    }

    /**
     * Historial explicativo del sistema cuando la papeleta no quedó en el
     * flujo "normal" (PendienteJefe decidido por un jefe disponible): jefe
     * de área (siempre) o jefe inmediato enviando su propia papeleta con su
     * Jefe de Área no disponible ahora (ver determinarEstadoInicial()).
     *
     * @param  array{directoARrhh: bool, jefeDisponible: bool, esJefeInmediatoDeLaUnidad: bool}  $jefatura
     */
    private function registrarHistorialDeEscalamiento(Papeleta $papeleta, array $jefatura, bool $autorizaSistema): void
    {
        if ($jefatura['directoARrhh']) {
            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => null,
                'actor_tipo' => 'sistema',
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $autorizaSistema
                    ? 'Jefe de área con RRHH fuera de horario: autorización automática, pendiente de revisión post-hoc.'
                    : 'Jefe de área: la papeleta se envía directo a RRHH.',
            ]);

            return;
        }

        if ($jefatura['esJefeInmediatoDeLaUnidad'] && ! $jefatura['jefeDisponible']) {
            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => null,
                'actor_tipo' => 'sistema',
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $autorizaSistema
                    ? 'Jefe inmediato fuera de su horario y RRHH fuera de horario: autorización automática, pendiente de revisión post-hoc.'
                    : 'Jefe inmediato fuera de su horario: la papeleta se envía directo a RRHH.',
            ]);

            return;
        }

        // Trabajador raso siempre queda en PendienteJefe sin historial
        // adicional: nunca escala, así que no hay nada que explicar aquí.
    }

    private function notificarCreacion(Papeleta $papeleta, string $estadoInicial, bool $autorizaSistema): void
    {
        match (true) {
            $autorizaSistema => (function () use ($papeleta) {
                $this->notificar->puedeSalir($papeleta);
                $this->notificar->revisionPosthocPendiente($papeleta);
            })(),
            $estadoInicial === PendienteRrhh::class => $this->notificar->pendienteDeRrhh($papeleta),
            default => $this->notificar->creada($papeleta),
        };
    }

    private function exigirSinPapeletaViva(User $trabajador): void
    {
        $viva = Papeleta::vivaDe($trabajador);

        if ($viva) {
            throw new PapeletaException($viva->mensajeDeBloqueoParaNueva());
        }
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
