<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un jefe inmediato asignado a UN turno (MANANA/TARDE/NOCHE) de UNA
 * unidad orgánica. Asignación manual — desde Avance 00.68 puede haber
 * varias filas por (unidad, turno): "el que actúa primero decide" (ver
 * UnidadOrganica::resolverJefesInmediatos(), fuente de verdad para
 * varios candidatos; resolverJefeInmediato() se mantiene para el caso
 * de uno solo, por compatibilidad).
 */
class JefeTurno extends Model
{
    protected $table = 'jefes_turno';

    protected $fillable = [
        'unidad_organica_id',
        'turno',
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
