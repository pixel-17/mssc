<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fotografía de UN candidato a jefe inmediato de UNA papeleta,
 * tomada al crearla (ver CrearPapeletaAction y
 * UnidadOrganica::resolverJefesInmediatos()).
 *
 * Cualquier fila de aquí puede decidir la papeleta — "el primero que
 * actúa, gana" — igual criterio que jefes_inmediatos_adicionales (ver
 * Papeleta::scopeDeJefeInmediato() y ::tieneComoJefeInmediatoA()).
 */
class PapeletaJefeCandidato extends Model
{
    protected $table = 'papeleta_jefes_candidatos';

    protected $fillable = [
        'papeleta_id',
        'user_id',
    ];

    public function papeleta(): BelongsTo
    {
        return $this->belongsTo(Papeleta::class);
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
