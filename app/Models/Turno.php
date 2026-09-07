<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horario de UN usuario en UNA fecha, cargado por el admin. Se usa para:
 * - Validar la ventana de creación de papeleta si el usuario es CAS.
 * - Decidir si un Jefe Inmediato/Jefe de Área/trabajador "está en su
 *   horario" al momento de escalar (cada actor se valida SOLO contra su
 *   propia fila, nunca contra el horario de otro actor).
 * Para 728 esta tabla es informativa: el sistema no valida nada contra
 * ella para ese régimen.
 */
class Turno extends Model
{
    protected $fillable = [
        'user_id',
        'sede_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'es_descanso',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'es_descanso' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
