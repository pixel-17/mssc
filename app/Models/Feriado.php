<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de feriados. Necesario para calcular correctamente los plazos
 * en "días/horas hábiles" (48h hábiles de sustento, 15 días hábiles de
 * subsanación de Emergencia).
 */
class Feriado extends Model
{
    protected $fillable = [
        'fecha',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }
}
