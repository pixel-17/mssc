<?php

namespace App\Models;

use App\Events\PapeletaActualizada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso 8: registro append-only de cada transición (quién, cuándo, estado
 * anterior/nuevo, motivo anterior/nuevo si cambió, justificación).
 * A propósito sin `updated_at` — a nivel de aplicación nunca se debe
 * hacer UPDATE ni DELETE sobre este modelo.
 *
 * @property int $id
 * @property int $papeleta_id
 * @property int|null $actor_id
 * @property 'trabajador'|'jefe_inmediato'|'jefe_area'|'rrhh'|'sistema' $actor_tipo
 * @property string|null $estado_anterior
 * @property string $estado_nuevo
 * @property int|null $motivo_anterior_id
 * @property int|null $motivo_nuevo_id
 * @property string|null $justificacion
 * @property array<string, mixed>|null $metadata
 * @property-read Papeleta $papeleta
 * @property-read User|null $actor
 * @property-read Motivo|null $motivoAnterior
 * @property-read Motivo|null $motivoNuevo
 */
class HistorialPapeleta extends Model
{
    const UPDATED_AT = null;

    protected $table = 'historial_papeletas';

    protected $fillable = [
        'papeleta_id',
        'actor_id',
        'actor_tipo',
        'estado_anterior',
        'estado_nuevo',
        'motivo_anterior_id',
        'motivo_nuevo_id',
        'justificacion',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Motivo, $this>
     */
    public function motivoAnterior(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_anterior_id');
    }

    /**
     * @return BelongsTo<Motivo, $this>
     */
    public function motivoNuevo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_nuevo_id');
    }

    protected static function booted(): void
    {
        // El historial se crea junto al cambio de estado; avisar aquí
        // garantiza que la línea de tiempo ya esté al refrescar.
        static::created(fn (self $evento) => PapeletaActualizada::notificar($evento->papeleta_id));
    }
}
