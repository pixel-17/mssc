<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $direccion
 * @property string $latitud
 * @property string $longitud
 * @property int $radio_metros
 * @property bool $activo
 * @property-read Collection<int, User> $usuarios
 * @property-read Collection<int, Papeleta> $papeletas
 */
class Sede extends Model
{
    protected $fillable = [
        'nombre',
        'direccion',
        'latitud',
        'longitud',
        'radio_metros',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Papeleta, $this>
     */
    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class);
    }
}
