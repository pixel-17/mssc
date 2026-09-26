<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un jefe inmediato ADICIONAL de UNA unidad orgánica (régimen 728).
 * Asignación manual de QUIÉN es jefe inmediato de esa unidad — ya NO
 * de qué turno fijo cubre: eso ya no se elige a mano aquí, sale
 * siempre de la programación de calendario propia del jefe
 * (`configuraciones_turno`, la misma que usan los trabajadores). Ver
 * UnidadOrganica::resolverJefeInmediato() / resolverJefesInmediatos()
 * y User::scopeDeLosTurnosQueCubre(), que comparan el turno vigente o
 * configurado del jefe contra el del trabajador. Puede haber varias
 * filas por unidad (varios jefes inmediatos adicionales); "el que
 * actúa primero decide" cuando coinciden en turno (ver
 * resolverJefesInmediatos()).
 */
class JefeTurno extends Model
{
    protected $table = 'jefes_turno';

    protected $fillable = [
        'unidad_organica_id',
        'jefe_id',
    ];

    public function unidadOrganica(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class);
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }
}
