<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nodo del organigrama (Gerencia, Sub Gerencia, Oficina General, Oficina,
 * oficina de apoyo/asesoramiento/control, etc). Árbol auto-referenciado
 * sin límite de niveles ni de cantidad de nodos: cualquier unidad puede
 * tener sub-oficinas creadas en cualquier momento.
 *
 * `tipo` es solo para pintar el organigrama con los colores de la leyenda
 * original — la lógica de escalamiento de papeletas NUNCA depende de él,
 * solo de la posición relativa en el árbol (ver jefeInmediatoDe() /
 * jefeAreaDe() más abajo).
 */
class UnidadOrganica extends Model
{
    protected $fillable = [
        'nombre',
        'tipo',
        'parent_id',
        'jefe_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'parent_id');
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }

    /** Trabajadores que pertenecen a esta unidad. */
    public function miembros(): HasMany
    {
        return $this->hasMany(User::class, 'unidad_organica_id');
    }

    /**
     * Jefe Inmediato de cualquier trabajador de esta unidad: el jefe de
     * la unidad misma.
     */
    public function jefeInmediato(): ?User
    {
        return $this->jefe;
    }

    /**
     * Jefe de Área de cualquier trabajador de esta unidad: el jefe de la
     * unidad padre. Si esta unidad no tiene padre (ej. Gerencia Municipal
     * colgando directo de Alcaldía), no hay Jefe de Área y el escalamiento
     * de esa unidad queda sin ese nivel.
     */
    public function jefeArea(): ?User
    {
        return $this->padre?->jefe;
    }

    /**
     * IDs de todos los descendientes (hijos, nietos, etc). Se usa para
     * impedir que al editar una unidad se le asigne como padre a sí misma
     * o a cualquiera de sus propios descendientes, lo que crearía un
     * ciclo infinito en el árbol.
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->hijos as $hijo) {
            $ids[] = $hijo->id;
            $ids = array_merge($ids, $hijo->descendantIds());
        }

        return $ids;
    }
}
