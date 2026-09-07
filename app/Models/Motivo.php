<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Particular, Salud, Comisión de Servicio o Emergencia. Las reglas de
 * negocio de cada uno (adjuntos, bypass de aprobación, exclusividad,
 * sustento, cierre sin retorno) viven como banderas en la fila, no
 * hardcodeadas por código -> nunca comparar $motivo->codigo === 'SALUD'
 * para decidir lógica, siempre usar las banderas.
 */
class Motivo extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'adjunto',
        'suma_descuento',
        'permite_bypass_aprobacion',
        'permite_cierre_sin_retorno',
        'requiere_sustento_en_retorno',
        'participa_regla_exclusividad',
        'es_destino_reclasificacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'suma_descuento' => 'boolean',
            'permite_bypass_aprobacion' => 'boolean',
            'permite_cierre_sin_retorno' => 'boolean',
            'requiere_sustento_en_retorno' => 'boolean',
            'participa_regla_exclusividad' => 'boolean',
            'es_destino_reclasificacion' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class);
    }
}
