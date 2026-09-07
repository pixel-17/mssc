<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso 5, motivo Salud: sin sustento al retorno -> RETORNO_PENDIENTE_SUSTENTO,
 * 48h hábiles para justificar. Vence sin nada -> reclasifica a Particular.
 * Si hay adjunto pendiente de revisión, el sistema no cierra solo, espera
 * confirmación humana (por eso 'presentado' está separado de 'aprobado').
 */
class Sustento extends Model
{
    protected $fillable = [
        'papeleta_id',
        'archivo_path',
        'fecha_limite',
        'estado',
        'presentado_at',
        'revisado_por_id',
        'revisado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'datetime',
            'presentado_at' => 'datetime',
            'revisado_at' => 'datetime',
        ];
    }

    public function papeleta(): BelongsTo
    {
        return $this->belongsTo(Papeleta::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }
}
