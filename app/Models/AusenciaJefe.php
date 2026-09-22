<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ausencia TEMPORAL de un jefe (vacaciones/permiso). Distinta de la baja
 * permanente (`users.activo`): mientras hay una fila vigente para un
 * jefe, CrearPapeletaAction no lo trata como decisor disponible y busca
 * un suplente (ver JefeSuplente) para (unidad, turno) del trabajador.
 *
 * Ver User::estaAusente(), que es la fuente única de "¿está ausente
 * este jefe en este momento?".
 */
class AusenciaJefe extends Model
{
    protected $table = 'ausencias_jefe';

    public const TIPO_VACACIONES_PERMISO = 'vacaciones_permiso';

    protected $fillable = [
        'jefe_id',
        'fecha_inicio',
        'fecha_fin',
        'tipo',
        'registrado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    /** ¿Este rango de ausencia cubre el momento dado? */
    public function cubre(Carbon $momento): bool
    {
        return $momento->toDateString() >= $this->fecha_inicio->toDateString()
            && $momento->toDateString() <= $this->fecha_fin->toDateString();
    }
}
