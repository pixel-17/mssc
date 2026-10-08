<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Particular, Salud o Comisión de Servicio. Las reglas de
 * negocio de cada uno (adjuntos, suma a descuento, sustento, cierre
 * sin retorno) viven como banderas en la fila, no
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
        'permite_cierre_sin_retorno',
        'requiere_sustento_en_retorno',
        'plazo_justificacion_horas_habiles',
        'es_destino_reclasificacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'suma_descuento' => 'boolean',
            'permite_cierre_sin_retorno' => 'boolean',
            'requiere_sustento_en_retorno' => 'boolean',
            'plazo_justificacion_horas_habiles' => 'integer',
            'es_destino_reclasificacion' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Horas hábiles que tiene el trabajador para presentar la justificación
     * una vez terminada la salida. Plazo propio del motivo; si el admin no
     * lo definió, el global SUSTENTO_HORAS_HABILES (48 por defecto).
     */
    public function plazoJustificacionHorasHabiles(): int
    {
        return $this->plazo_justificacion_horas_habiles
            ?? (int) Configuracion::valorDe('SUSTENTO_HORAS_HABILES', 48);
    }

    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class);
    }
}
