<?php

namespace App\Models;

use App\Events\PapeletaActualizada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso 5: evidencia normal es foto + GPS + hora del servidor simultáneos.
 * Única excepción: falla de conectividad -> jefe marca manual, sin
 * foto/GPS, con justificación obligatoria y alerta posterior a RRHH.
 *
 * @property int $id
 * @property int $papeleta_id
 * @property string|null $foto_path
 * @property string|null $latitud
 * @property string|null $longitud
 * @property bool|null $dentro_de_radio
 * @property \Illuminate\Support\Carbon|null $hora_servidor
 * @property bool $marcado_manual
 * @property bool $generado_por_abandono
 * @property int|null $marcado_manual_por_id
 * @property string|null $justificacion_manual
 * @property-read Papeleta $papeleta
 * @property-read User|null $marcadoManualPor
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
        'generado_por_abandono',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
            'dentro_de_radio' => 'boolean',
            'hora_servidor' => 'datetime',
            'marcado_manual' => 'boolean',
            'generado_por_abandono' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Papeleta, $this>
     */
    public function papeleta(): BelongsTo
    {
        return $this->belongsTo(Papeleta::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function marcadoManualPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marcado_manual_por_id');
    }

    protected static function booted(): void
    {
        static::saved(fn (self $retorno) => PapeletaActualizada::notificar($retorno->papeleta_id));
    }
}
