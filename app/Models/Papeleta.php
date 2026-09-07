<?php

namespace App\Models;

use App\States\Papeleta\PapeletaState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

/**
 * Núcleo del flujo. Sede/régimen/día operativo son la "fotografía
 * inmutable" tomada al crear (Paso 1) — no se actualizan si el
 * trabajador cambia de sede o de régimen después.
 *
 * La máquina de estados (transiciones válidas, ver PapeletaState::config())
 * vive conectada aquí vía HasStates; el RELOJ de 5 min, el escalamiento,
 * etc. viven en Actions/Console\Commands, no en el modelo.
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
        'estado',
        'es_emergencia',
        'slot_normal_activo',
        'slot_emergencia_activo',
        'jefe_inmediato_id',
        'resuelto_por_jefe_id',
        'jefe_resuelto_at',
        'contador_observaciones_jefe',
        'jefe_area_id',
        'escalado_jefe_area_at',
        'resuelto_por_rrhh_id',
        'rrhh_resuelto_at',
        'contador_observaciones_rrhh',
        'autorizado_con_rrhh_fuera_horario',
        'revision_posthoc_estado',
        'revision_posthoc_por_id',
        'revision_posthoc_at',
        'hora_salida_real',
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
    ];

    protected function casts(): array
    {
        return [
            'estado' => PapeletaState::class,
            'dia_operativo' => 'date',
            'es_emergencia' => 'boolean',
            'slot_normal_activo' => 'boolean',
            'slot_emergencia_activo' => 'boolean',
            'jefe_resuelto_at' => 'datetime',
            'escalado_jefe_area_at' => 'datetime',
            'rrhh_resuelto_at' => 'datetime',
            'autorizado_con_rrhh_fuera_horario' => 'boolean',
            'revision_posthoc_at' => 'datetime',
            'hora_salida_real' => 'datetime',
            'cancelada_at' => 'datetime',
            'vencida_at' => 'datetime',
            'requiere_visto_bueno' => 'boolean',
            'regularizacion_fecha_limite' => 'datetime',
        ];
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
