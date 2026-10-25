<?php

namespace App\Models;

use App\Events\PapeletaActualizada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso 5, motivo Salud: sin sustento al retorno -> RETORNO_PENDIENTE_SUSTENTO,
 * 48h hábiles para justificar. Vence sin nada -> reclasifica a Particular.
 * Si hay adjunto pendiente de revisión, el sistema no cierra solo, espera
 * confirmación humana (por eso 'presentado' está separado de 'aprobado').
 *
 * @property int $id
 * @property int $papeleta_id
 * @property string|null $archivo_path
 * @property \Illuminate\Support\Carbon $fecha_limite
 * @property 'pendiente'|'presentado'|'vencido'|'aprobado'|'observado'|'rechazado' $estado
 * @property \Illuminate\Support\Carbon|null $presentado_at
 * @property int|null $revisado_por_id
 * @property \Illuminate\Support\Carbon|null $revisado_at
 * @property-read Papeleta $papeleta
 * @property-read User|null $revisadoPor
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
    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    protected static function booted(): void
    {
        // El sustento cambia lo que ven trabajador, jefe y RRHH de la
        // papeleta (presentado, aprobado, observado...): avisar en vivo.
        static::saved(fn (self $sustento) => PapeletaActualizada::notificar($sustento->papeleta_id));
    }
}
