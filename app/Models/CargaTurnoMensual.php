<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un mes ya generado para un trabajador. Ver migración: el unique
 * (user_id, anio, mes) es lo que le dice al comando automático que
 * ese mes ya está resuelto (manual o automático) y no debe tocarlo.
 */
class CargaTurnoMensual extends Model
{
    protected $table = 'cargas_turno_mensuales';

    protected $fillable = [
        'user_id',
        'anio',
        'mes',
        'origen',
        'configuracion_turno_id',
        'generado_por_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function configuracionTurno(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionTurno::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }
}
