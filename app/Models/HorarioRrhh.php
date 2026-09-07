<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Horario único de RRHH para toda la municipalidad, por día de semana.
 * Se consulta en cada aprobación de jefe para decidir si la papeleta va
 * a PENDIENTE_RRHH o directo a AUTORIZADA_Y_CORRIENDO (Paso 2), y para
 * armar la bandeja de revisión post-hoc (Paso 4).
 */
class HorarioRrhh extends Model
{
    protected $table = 'horarios_rrhh';

    protected $fillable = [
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
