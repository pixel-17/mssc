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
     * [jefe_inmediato_id, jefe_area_id] de $usuario como miembro de esta
     * unidad. Es la ÚNICA fuente de verdad de esta regla: la usan
     * UserObserver (columnas vigentes en `users`), CrearPapeletaAction
     * (fotografía en la papeleta) y el comando jefaturas:recalcular,
     * para que nunca diverjan.
     *
     * Quien encabeza la unidad también es miembro de ella, pero NO puede
     * ser su propio jefe: su superior está un nivel arriba (jefe
     * inmediato = jefe de la unidad padre; jefe de área = jefe de la
     * unidad abuela). Si no hay unidad padre, ese nivel queda en null.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public function jefaturasDe(User $usuario): array
    {
        $padre = $this->padre;

        if ($usuario->id !== null && (int) $this->jefe_id === (int) $usuario->id) {
            return [
                $padre?->jefe_id !== null ? (int) $padre->jefe_id : null,
                $padre?->padre?->jefe_id !== null ? (int) $padre->padre->jefe_id : null,
            ];
        }

        return [
            $this->jefe_id !== null ? (int) $this->jefe_id : null,
            $padre?->jefe_id !== null ? (int) $padre->jefe_id : null,
        ];
    }

    /**
     * IDs de todos los descendientes (hijos, nietos, etc). Se usa para
     * impedir que al editar una unidad se le asigne como padre a sí misma
     * o a cualquiera de sus propios descendientes, lo que crearía un
     * ciclo infinito en el árbol.
     *
     * Trae TODAS las unidades en una sola query (son las oficinas del
     * organigrama, no trabajadores — un volumen chico) y arma el árbol
     * en memoria. La versión anterior recorría `hijos` recursivamente y
     * hacía 1 query por cada nodo descendiente; con un organigrama de
     * varios niveles esto se sentía en cada request que llama a esto
     * (UsuarioController, CrearUsuarioRequest, CalendarioEquipoIndex).
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $porPadre = static::query()
            ->select('id', 'parent_id')
            ->get()
            ->groupBy('parent_id');

        $ids = [];
        $pendientes = [$this->id];

        while ($pendientes) {
            $idActual = array_pop($pendientes);

            foreach ($porPadre->get($idActual, []) as $hijo) {
                $ids[] = $hijo->id;
                $pendientes[] = $hijo->id;
            }
        }

        return $ids;
    }
}
