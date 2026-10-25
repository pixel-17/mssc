<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento de un trabajador entre unidades orgánicas, hecho desde
 * el organigrama. Registro de solo agregar: lo único que se modifica
 * después de creado es la marca `deshecho_at` / `deshecho_por_id` del
 * original cuando alguien lo deshace (ver MoverTrabajadorAction).
 *
 * `deshecho_*` no están en $fillable a propósito: solo la Action las
 * escribe, con forceFill().
 *
 * @property int $id
 * @property int|null $trabajador_id
 * @property int|null $actor_id
 * @property int|null $unidad_anterior_id
 * @property int|null $unidad_nueva_id
 * @property int|null $sede_anterior_id
 * @property int|null $sede_nueva_id
 * @property int|null $jefe_inmediato_anterior_id
 * @property int|null $jefe_inmediato_nuevo_id
 * @property int|null $jefe_area_anterior_id
 * @property int|null $jefe_area_nuevo_id
 * @property int|null $revierte_id
 * @property \Illuminate\Support\Carbon|null $deshecho_at
 * @property int|null $deshecho_por_id
 * @property-read User|null $trabajador
 * @property-read User|null $actor
 * @property-read UnidadOrganica|null $unidadAnterior
 * @property-read UnidadOrganica|null $unidadNueva
 * @property-read Sede|null $sedeAnterior
 * @property-read Sede|null $sedeNueva
 * @property-read User|null $jefeInmediatoAnterior
 * @property-read User|null $jefeInmediatoNuevo
 * @property-read User|null $jefeAreaAnterior
 * @property-read User|null $jefeAreaNuevo
 * @property-read User|null $deshechoPor
 * @property-read MovimientoOrganigrama|null $revierte
 */
class MovimientoOrganigrama extends Model
{
    const UPDATED_AT = null;

    protected $table = 'movimientos_organigrama';

    protected $fillable = [
        'trabajador_id',
        'actor_id',
        'unidad_anterior_id',
        'unidad_nueva_id',
        'sede_anterior_id',
        'sede_nueva_id',
        'jefe_inmediato_anterior_id',
        'jefe_inmediato_nuevo_id',
        'jefe_area_anterior_id',
        'jefe_area_nuevo_id',
        'revierte_id',
    ];

    protected function casts(): array
    {
        return [
            'deshecho_at' => 'datetime',
            // list<array{jefe_id: int, asignado_por_id: ?int}>|null
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trabajador_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<UnidadOrganica, $this>
     */
    public function unidadAnterior(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'unidad_anterior_id');
    }

    /**
     * @return BelongsTo<UnidadOrganica, $this>
     */
    public function unidadNueva(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'unidad_nueva_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sedeAnterior(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_anterior_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sedeNueva(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_nueva_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeInmediatoAnterior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_anterior_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeInmediatoNuevo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_nuevo_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeAreaAnterior(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_anterior_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jefeAreaNuevo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_nuevo_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deshechoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deshecho_por_id');
    }

    /** Fila original a la que esta revierte (si es una reversión). */
    /**
     * @return BelongsTo<MovimientoOrganigrama, $this>
     */
    public function revierte(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revierte_id');
    }

    public function cambioSede(): bool
    {
        return (int) $this->sede_anterior_id !== (int) $this->sede_nueva_id;
    }

    public function esReversion(): bool
    {
        return $this->revierte_id !== null;
    }
}
