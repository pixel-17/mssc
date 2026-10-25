<?php

namespace App\Models;

use App\Events\PapeletaActualizada;
use App\Exceptions\PapeletaException;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PapeletaState;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property int|null $en_revision_por_id
 * @property \Illuminate\Support\Carbon|null $en_revision_desde
 * @property int $trabajador_id
 * @property int $motivo_id
 * @property int $sede_id
 * @property '276'|'728' $regimen
 * @property \Illuminate\Support\Carbon $dia_operativo
 * @property \Illuminate\Support\Carbon|null $fin_turno_at
 * @property PapeletaState $estado
 * @property int|null $jefe_inmediato_id
 * @property int|null $resuelto_por_jefe_id
 * @property \Illuminate\Support\Carbon|null $jefe_resuelto_at
 * @property int $contador_observaciones_jefe
 * @property bool $observacion_requiere_adjunto
 * @property string|null $observacion_adjunto_path
 * @property \Illuminate\Support\Carbon|null $observacion_subsanada_at
 * @property string|null $observacion_respuesta
 * @property \Illuminate\Support\Carbon|null $reloj_jefe_at
 * @property int|null $jefe_area_id
 * @property int|null $resuelto_por_rrhh_id
 * @property \Illuminate\Support\Carbon|null $rrhh_resuelto_at
 * @property int $contador_observaciones_rrhh
 * @property bool $autorizado_con_rrhh_fuera_horario
 * @property 'no_aplica'|'pendiente'|'observada'|'respondida'|'aprobada'|'observada_firme'|'no_aprobada' $revision_posthoc_estado
 * @property int|null $revision_posthoc_por_id
 * @property \Illuminate\Support\Carbon|null $revision_posthoc_at
 * @property int $contador_observaciones_posthoc
 * @property string|null $posthoc_observacion
 * @property string|null $posthoc_respuesta
 * @property string|null $posthoc_adjunto_path
 * @property \Illuminate\Support\Carbon|null $posthoc_respondida_at
 * @property \Illuminate\Support\Carbon|null $hora_salida_real
 * @property int $descuento_refrigerio_minutos
 * @property int|null $rechazada_por_id
 * @property string|null $motivo_rechazo
 * @property \Illuminate\Support\Carbon|null $cancelada_at
 * @property \Illuminate\Support\Carbon|null $vencida_at
 * @property string|null $causa_finalizacion_sin_retorno
 * @property bool $requiere_visto_bueno
 * @property \Illuminate\Support\Carbon|null $regularizacion_fecha_limite
 * @property int|null $motivo_original_id
 * @property string|null $justificacion
 * @property \Illuminate\Support\Carbon|null $hora_retorno_estimado
 * @property string|null $adjunto_inicial_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $trabajador
 * @property-read Motivo $motivo
 * @property-read Motivo|null $motivoOriginal
 * @property-read Sede $sede
 * @property-read User|null $jefeInmediato
 * @property-read User|null $resueltoPorJefe
 * @property-read User|null $jefeArea
 * @property-read User|null $resueltoPorRrhh
 * @property-read User|null $revisionPosthocPor
 * @property-read User|null $rechazadaPor
 * @property-read User|null $enRevisionPor
 * @property-read Retorno|null $retorno
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Sustento> $sustentos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, HistorialPapeleta> $historial
 */
class Papeleta extends Model
{
    use HasStates;

    protected $fillable = [
        'trabajador_id',
        'motivo_id',
        'sede_id',
        'regimen',
        'dia_operativo',
        'fin_turno_at',
        'estado',
        'jefe_inmediato_id',
        'resuelto_por_jefe_id',
        'jefe_resuelto_at',
        'contador_observaciones_jefe',
        'jefe_area_id',
        'resuelto_por_rrhh_id',
        'rrhh_resuelto_at',
        'contador_observaciones_rrhh',
        'autorizado_con_rrhh_fuera_horario',
        'revision_posthoc_estado',
        'revision_posthoc_por_id',
        'revision_posthoc_at',
        'contador_observaciones_posthoc',
        'posthoc_observacion',
        'posthoc_respuesta',
        'posthoc_adjunto_path',
        'posthoc_respondida_at',
        'hora_salida_real',
        'hora_retorno_estimado',
        'descuento_refrigerio_minutos',
        'rechazada_por_id',
        'motivo_rechazo',
        'cancelada_at',
        'vencida_at',
        'causa_finalizacion_sin_retorno',
        'requiere_visto_bueno',
        'regularizacion_fecha_limite',
        'motivo_original_id',
        'justificacion',
        'adjunto_inicial_path',
        'observacion_requiere_adjunto',
        'observacion_respuesta',
        'observacion_adjunto_path',
        'observacion_subsanada_at',
        'reloj_jefe_at',
        'en_revision_por_id',
        'en_revision_desde',
    ];

    protected function casts(): array
    {
        return [
            'estado' => PapeletaState::class,
            'dia_operativo' => 'date',
            'fin_turno_at' => 'datetime',
            'jefe_resuelto_at' => 'datetime',
            'rrhh_resuelto_at' => 'datetime',
            'autorizado_con_rrhh_fuera_horario' => 'boolean',
            'revision_posthoc_at' => 'datetime',
            'posthoc_respondida_at' => 'datetime',
            'hora_salida_real' => 'datetime',
            'hora_retorno_estimado' => 'datetime',
            'cancelada_at' => 'datetime',
            'vencida_at' => 'datetime',
            'requiere_visto_bueno' => 'boolean',
            'regularizacion_fecha_limite' => 'datetime',
            'observacion_requiere_adjunto' => 'boolean',
            'observacion_subsanada_at' => 'datetime',
            'reloj_jefe_at' => 'datetime',
            'en_revision_desde' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Tiempo real: cualquier cambio de la papeleta (estado, retorno,
        // observaciones...) se publica por Reverb, ver PapeletaActualizada.
        static::saved(fn (self $papeleta) => PapeletaActualizada::notificar($papeleta->id));
    }

    /**
     * Único punto de cambio de estado. Valida contra
     * PapeletaState::config() (antes las Actions asignaban
     * `->estado = new X(...)` y ninguna transición inválida se bloqueaba).
     * No guarda: el llamador sigue haciendo save() dentro de su transacción.
     *
     * @param  class-string<PapeletaState>  $destino
     */
    public function transicionarA(string $destino): void
    {
        if (! $this->estado->canTransitionTo($destino)) {
            throw new PapeletaException(sprintf(
                'Transición de estado no permitida: %s -> %s.',
                class_basename($this->estado),
                class_basename($destino),
            ));
        }

        $this->estado = new $destino($this);
    }

    /**
     * Único camino para mover una papeleta de estado dejando rastro: valida la
     * transición, aplica los cambios de columnas que la acompañan, guarda y
     * escribe la fila de HistorialPapeleta. Debe llamarse dentro de la
     * transacción que ya tiene la fila bloqueada (lockForUpdate).
     *
     * @param  class-string<PapeletaState>  $destino
     * @param  \Closure(self): void|null  $cambios  Columnas extra a fijar antes de guardar (en la papeleta).
     * @param  array{motivo_anterior_id?: int|null, motivo_nuevo_id?: int|null, metadata?: array<string, mixed>|null}|null  $camposHistorial  Columnas propias de HistorialPapeleta (no de la papeleta), p. ej. cuando la transición cambia de motivo.
     */
    public function transicionarConHistorial(
        string $destino,
        ?int $actorId,
        string $actorTipo,
        ?string $justificacion = null,
        ?\Closure $cambios = null,
        ?array $camposHistorial = null,
    ): HistorialPapeleta {
        $estadoAnterior = class_basename($this->estado);

        $this->transicionarA($destino);

        if ($cambios) {
            $cambios($this);
        }

        $this->save();

        return HistorialPapeleta::create([
            'papeleta_id' => $this->id,
            'actor_id' => $actorId,
            'actor_tipo' => $actorTipo,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => class_basename($this->estado),
            'justificacion' => $justificacion,
            'motivo_anterior_id' => $camposHistorial['motivo_anterior_id'] ?? null,
            'motivo_nuevo_id' => $camposHistorial['motivo_nuevo_id'] ?? null,
            'metadata' => $camposHistorial['metadata'] ?? null,
        ]);
    }

    /**
     * El trabajador salió y no regresó (abandono). El estado dice cómo
     * terminó: EnJustificacion (aún puede justificar), Cerrada (abandono
     * justificado, sin descuento) o Finalizada (sin justificar, con
     * descuento). La causa es la única pregunta que debe hacer cualquier vista.
     */
    public function esAbandono(): bool
    {
        return $this->causa_finalizacion_sin_retorno === 'abandono_no_marcado';
    }

    /**
     * Estados en los que la papeleta sigue "viva": todavía no se resolvió o
     * el trabajador está fuera. Un trabajador solo puede tener UNA papeleta
     * viva a la vez; para crear otra debe cancelar (solo mientras espera
     * decisión) o finalizar la que tiene (marcando su retorno).
     *
     * EnJustificacion no cuenta: la salida ya terminó y solo falta presentar
     * el sustento. Los terminales (Cerrada, Finalizada, Cancelada, Vencida,
     * Rechazada) tampoco.
     */
    public const ESTADOS_VIVOS = [
        PendienteJefe::class,
        ObservadaPorJefe::class,
        PendienteRrhh::class,
        ObservadaPorRrhh::class,
        AutorizadaYCorriendo::class,
    ];

    public function scopeVivas(Builder $query): Builder
    {
        return $query->whereState('estado', self::ESTADOS_VIVOS);
    }

    /** La papeleta viva del trabajador (la más reciente), o null si no tiene ninguna. */
    public static function vivaDe(User $trabajador): ?self
    {
        return static::where('trabajador_id', $trabajador->id)->vivas()->latest('id')->first();
    }

    /** Texto para el trabajador cuando intenta crear otra papeleta teniendo esta viva. */
    public function mensajeDeBloqueoParaNueva(): string
    {
        if ($this->estado->equals(AutorizadaYCorriendo::class)) {
            return "Ya tienes la papeleta #{$this->id} autorizada y en curso. Finalízala (marca tu retorno) antes de crear otra.";
        }

        return "Ya tienes la papeleta #{$this->id} en trámite. Cancélala o espera su resolución antes de crear otra.";
    }

    /**
     * Papeletas cuyo turno/día ya terminó según fin_turno_at. Las que no
     * tienen fin_turno_at (legadas) se incluyen siempre: ahí decide
     * DeterminadorFinDeTurno en PHP. Permite que los comandos del minuto
     * filtren en SQL (índice estado + fin_turno_at) en vez de cargar todas.
     */
    public function scopeConTurnoTerminado(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('papeletas.fin_turno_at')
            ->orWhere('papeletas.fin_turno_at', '<', now()));
    }

    /** Complemento de scopeConTurnoTerminado: turno aún vigente (o legada sin fin_turno_at). */
    public function scopeConTurnoSinTerminar(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('papeletas.fin_turno_at')
            ->orWhere('papeletas.fin_turno_at', '>=', now()));
    }

    /**
     * Papeletas que el usuario decide como jefe inmediato: las que tienen su
     * id en jefe_inmediato_id. Es UN solo jefe por papeleta; si la jefatura
     * de la unidad cambia, las papeletas abiertas se reasignan al jefe nuevo
     * (ver ReasignarPapeletasAbiertasAction) y solo él las decide.
     */
    public function scopeDeJefeInmediato(Builder $query, User $jefe): Builder
    {
        return $query->where('papeletas.jefe_inmediato_id', $jefe->id);
    }

    /**
     * Papeletas del turno vigente: las que aún no terminan según
     * fin_turno_at. Sin fin_turno_at (papeletas viejas) se usa el día
     * operativo de hoy. Lo usan "Mis papeletas" y la bandeja del Jefe.
     */
    public function scopeDelTurnoVigente(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('papeletas.fin_turno_at', '>', now())
            ->orWhere(fn (Builder $q2) => $q2
                ->whereNull('papeletas.fin_turno_at')
                ->whereDate('papeletas.dia_operativo', today())));
    }

    /**
     * Fuente única de "papeletas de mi equipo" para reportes y dashboards.
     * Une las columnas fotografiadas (historial estable si cambia el
     * organigrama) con el equipo vivo, que sí incluye a los trabajadores
     * a las unidades hijas.
     */
    public function scopeDeEquipoDe(Builder $query, User $jefe): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('papeletas.jefe_inmediato_id', $jefe->id)
            ->orWhere('papeletas.jefe_area_id', $jefe->id)
            ->orWhereIn('papeletas.trabajador_id', User::equipoDe($jefe)->select('users.id')));
    }

    /**
     * ¿Este usuario es el Jefe Inmediato que decide ESTA papeleta? Compara
     * contra jefe_inmediato_id de la papeleta (ver scopeDeJefeInmediato).
     */
    public function tieneComoJefeInmediatoA(User $user): bool
    {
        return $this->jefe_inmediato_id !== null && (int) $this->jefe_inmediato_id === (int) $user->id;
    }

    /**
     * true si esta papeleta se fotografió sin ningún jefe superior (ni
     * inmediato ni de área) — el caso del tope del organigrama, o
     * cualquier otra papeleta creada sin jefatura resuelta. Se usa para
     * saber si hay a quién devolverle una observación de RRHH
     * (ObservarRrhhAction) o para reportes; se calcula contra las
     * columnas fotografiadas, no se vuelve a resolver la unidad actual.
     */
    public function sinJefatura(): bool
    {
        return $this->jefe_inmediato_id === null && $this->jefe_area_id === null;
    }

    /**
     * Revisión post-hoc observada por RRHH: solo el MISMO jefe que
     * autorizó la papeleta (resuelto_por_jefe_id) puede responderla,
     * no cualquier otro jefe de la unidad.
     */
    public function puedeResponderPosthoc(User $user): bool
    {
        return $this->autorizado_con_rrhh_fuera_horario
            && $this->revision_posthoc_estado === 'observada'
            && $this->resuelto_por_jefe_id !== null
            && (int) $this->resuelto_por_jefe_id === (int) $user->id
            && (int) $this->trabajador_id !== (int) $user->id;
    }

    /** Texto legible del estado de la revisión post-hoc (para las vistas). */
    public function etiquetaPosthoc(): string
    {
        return match ($this->revision_posthoc_estado) {
            'observada' => 'Observada — espera respuesta del jefe',
            'respondida' => 'Respondida por el jefe — pendiente de RRHH',
            'observada_firme' => 'Observada (reparo definitivo) — pasó a Particular',
            'no_aprobada' => 'No aprobada — pasó a Particular',
            'aprobada' => 'Aprobada',
            'no_aplica' => 'No aplica',
            default => 'Pendiente',
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trabajador_id');
    }

    /**
     * @return BelongsTo<Motivo, $this>
     */
    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class);
    }

    /**
     * @return BelongsTo<Motivo, $this>
     */
    public function motivoOriginal(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_original_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeInmediato(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resueltoPorJefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_jefe_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeArea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resueltoPorRrhh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_rrhh_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revisionPosthocPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revision_posthoc_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rechazadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazada_por_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enRevisionPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'en_revision_por_id');
    }

    /**
     * @return HasOne<Retorno, $this>
     */
    public function retorno(): HasOne
    {
        return $this->hasOne(Retorno::class);
    }

    /**
     * @return HasMany<Sustento, $this>
     */
    public function sustentos(): HasMany
    {
        return $this->hasMany(Sustento::class);
    }

    /**
     * @return HasMany<HistorialPapeleta, $this>
     */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialPapeleta::class);
    }
}
