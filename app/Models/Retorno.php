<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso 5: evidencia normal es foto + GPS + hora del servidor simultáneos.
 * Única excepción: falla de conectividad -> jefe marca manual, sin
 * foto/GPS, con justificación obligatoria y alerta posterior a RRHH.
 */
class Retorno extends Model
{
    protected $fillable = [
        'papeleta_id',
        'foto_path',
        'latitud',
        'longitud',
        'dentro_de_radio',
        'hora_servidor',
        'marcado_manual',
        'marcado_manual_por_id',
        'justificacion_manual',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
            'dentro_de_radio' => 'boolean',
            'hora_servidor' => 'datetime',
            'marcado_manual' => 'boolean',
        ];
    }

    public function papeleta(): BelongsTo
    {
        return $this->belongsTo(Papeleta::class);
    }

    public function marcadoManualPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marcado_manual_por_id');
    }
}
