<?php

namespace App\Models;

use App\Exceptions\PapeletaException;
use App\States\Papeleta\PapeletaState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\HasStates;

/**
 * Núcleo del flujo. Sede/régimen/día operativo son la "fotografía
 * inmutable" tomada al crear (Paso 1) — no se actualizan si el
 * trabajador cambia de sede o de régimen después.
 *
 * La máquina de estados (transiciones válidas, ver PapeletaState::config())
 * vive conectada aquí vía HasStates; el vencimiento por fin de turno/día,
 * etc. viven en Actions/Console\Commands, no en el modelo.
 *
 * Decidir sobre una papeleta como jefe es SIEMPRE responsabilidad del
 * Jefe Inmediato (jefe_inmediato_id): no escala a nadie más por
 * inacción, ni siquiera al Jefe de Área. jefe_area_id se mantiene solo
 * para reportes/dashboards (scopeDeEquipoDe) y para asignar jefes
 * inmediatos adicionales (AsignarJefeAdicionalAction).
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
            'hora_salida_real' => 'datetime',
            'hora_retorno_estimado' => 'datetime',
            'cancelada_at' => 'datetime',
            'vencida_at' => 'datetime',
            'requiere_visto_bueno' => 'boolean',
            'regularizacion_fecha_limite' => 'datetime',
            'observacion_requiere_adjunto' => 'boolean',
            'observacion_subsanada_at' => 'datetime',
            'reloj_jefe_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Tiempo real: cualquier cambio de la papeleta (estado, retorno,
        // observaciones...) se publica por Reverb, ver PapeletaActualizada.
        static::saved(fn (self $papeleta) => \App\Events\PapeletaActualizada::notificar($papeleta->id));
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

    /** Papeletas que el usuario decide como jefe inmediato (automático o adicional). */
    public function scopeDeJefeInmediato(Builder $query, User $jefe): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('papeletas.jefe_inmediato_id', $jefe->id)
            ->orWhereIn('papeletas.trabajador_id', DB::table('jefes_inmediatos_adicionales')
                ->where('jefe_inmediato_id', $jefe->id)
                ->select('trabajador_id')));
    }

    /**
     * Fuente única de "papeletas de mi equipo" para reportes y dashboards.
     * Une las columnas fotografiadas (historial estable si cambia el
     * organigrama) con el equipo vivo, que sí incluye a los trabajadores
     * asignados como jefe adicional y a las unidades hijas.
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
     * contra la columna fotografiada (jefe_inmediato_id, resuelta por
     * turno en CrearPapeletaAction vía jefes_turno) más los adicionales
     * vigentes — mismo criterio que scopeDeJefeInmediato, para que quien
     * ve la papeleta en su bandeja sea siempre quien puede decidirla.
     * No usar User::esJefeInmediatoDe() aquí: esa compara contra
     * users.jefe_inmediato_id, la columna estática que UserObserver
     * calcula SIN turno y por lo tanto no refleja jefes_turno.
     */
    public function tieneComoJefeInmediatoA(User $user): bool
    {
        return static::whereKey($this->id)->deJefeInmediato($user)->exists();
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

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trabajador_id');
    }

    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class);
    }

    public function motivoOriginal(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_original_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function jefeInmediato(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_id');
    }

    public function resueltoPorJefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_jefe_id');
    }

    public function jefeArea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    public function resueltoPorRrhh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por_rrhh_id');
    }

    public function revisionPosthocPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revision_posthoc_por_id');
    }

    public function rechazadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazada_por_id');
    }

    public function retorno(): HasOne
    {
        return $this->hasOne(Retorno::class);
    }

    public function sustentos(): HasMany
    {
        return $this->hasMany(Sustento::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialPapeleta::class);
    }
}