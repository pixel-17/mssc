<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * @property int $id
 * @property string $nombre
 * @property string|null $direccion
 * @property string|null $latitud
 * @property string|null $longitud
 * @property int|null $radio_metros
 * @property bool $activo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $usuarios
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Papeleta> $papeletas
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

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class);
    }
}
