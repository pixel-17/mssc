<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jefe inmediato de UN turno (MANANA/TARDE/NOCHE) de UNA unidad
 * orgánica. Asignación fija, manual — no hay ausencia ni suplente
 * automático (ver UnidadOrganica::resolverJefeInmediato(), que es la
 * única fuente de verdad que lee esta tabla).
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
