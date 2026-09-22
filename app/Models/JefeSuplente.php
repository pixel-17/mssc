<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Jefe suplente configurado para una unidad orgánica en un turno dado
 * ('MANANA'|'TARDE'|'NOCHE'|'DIA' — mismos códigos que Turno::codigo() /
 * ConfiguracionTurno). Puede haber varias filas por (unidad, turno):
 * `orden` define la prioridad con la que se prueban (ver
 * candidatosPara()).
 *
 * Solo se consultan cuando el jefe titular de esa unidad está en
 * ausencia temporal (ver AusenciaJefe / User::estaAusente()) —
 * CrearPapeletaAction es quien decide, probando cada candidato hasta
 * encontrar uno disponible (DecisorDisponibleService) y no ausente él
 * mismo.
 */
class JefeSuplente extends Model
{
    protected $table = 'jefes_suplentes';

    protected $fillable = [
        'unidad_organica_id',
        'turno',
        'jefe_suplente_id',
        'orden',
    ];

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'unidad_organica_id');
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_suplente_id');
    }

    /**
     * Candidatos a suplente para (unidad, turno), en orden de prioridad
     * (`orden` ascendente). CrearPapeletaAction prueba cada uno hasta
     * encontrar el primero disponible.
     *
     * @return Collection<int, User>
     */
    public static function candidatosPara(int $unidadOrganicaId, string $turno): Collection
    {
        return static::where('unidad_organica_id', $unidadOrganicaId)
            ->where('turno', $turno)
            ->orderBy('orden')
            ->with('jefe')
            ->get()
            ->pluck('jefe')
            ->filter()
            ->values();
    }
}
