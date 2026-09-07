<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
