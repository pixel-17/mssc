<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $tipo
 * @property int|null $parent_id
 * @property int|null $jefe_id
 * @property bool $activo
 * @property-read UnidadOrganica|null $padre
 * @property-read User|null $jefe
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $miembros
 */
class UnidadOrganica extends Model
{
    /** Espeja el default de la BD para que un modelo recién creado ya tenga activo = true. */
    protected $attributes = [
        'activo' => true,
    ];

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

    /**
     * @return BelongsTo<UnidadOrganica, $this>
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class, 'parent_id');
    }

    /**
     * @return HasMany<UnidadOrganica, $this>
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_id');
    }

    /**
     * ¿$usuario es el jefe de ESTA unidad? Un único jefe (`jefe_id`), sin
     * importar su régimen ni el de su gente.
     */
    public function esJefeDeLaUnidad(User $usuario): bool
    {
        return $this->jefe_id !== null && (int) $this->jefe_id === (int) $usuario->id;
    }

    /** Trabajadores que pertenecen a esta unidad. */
    /**
     * @return HasMany<User, $this>
     */
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
     * Quien encabeza la unidad (`jefe_id`)
     * también es miembro de ella, pero NO puede ser su propio jefe: su
     * superior está un nivel arriba (jefe
     * inmediato = jefe de la unidad padre; jefe de área = jefe de la
     * unidad abuela). Si no hay unidad abuela (o no hay unidad padre, para
     * quien encabeza su propia unidad), ya no queda en null: se autocompleta
     * con el mismo jefe inmediato resuelto, para no mostrarlo en blanco.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public function jefaturasDe(User $usuario): array
    {
        $padre = $this->padre;

        if ($this->esJefeDeLaUnidad($usuario)) {
            $jefeInmediatoId = $padre?->jefe_id !== null ? (int) $padre->jefe_id : null;
            $jefeAreaId = $padre?->padre?->jefe_id !== null ? (int) $padre->padre->jefe_id : null;

            return [$jefeInmediatoId, $jefeAreaId ?? $jefeInmediatoId];
        }

        $jefeInmediatoId = $this->jefe_id !== null ? (int) $this->jefe_id : null;
        $jefeAreaId = $padre?->jefe_id !== null ? (int) $padre->jefe_id : null;

        // Sin unidad padre (ej. una Gerencia que cuelga directo de la
        // Alcaldía) no hay un tercer nivel que resolver como Jefe de Área:
        // en vez de dejarlo en blanco, se autocompleta con el mismo jefe
        // inmediato (quien, al no tener nadie más arriba, hace también de
        // Jefe de Área). Afecta solo a jefes inmediatos enviando su propia
        // papeleta y a trabajadores raso que dependen directo de él.
        return [$jefeInmediatoId, $jefeAreaId ?? $jefeInmediatoId];
    }

    /**
     * Todas las unidades en memoria, por id, con lo que jefaturasDe()
     * necesita ya cargado: `jefe` y `padre` enlazado a la
     * misma instancia del mapa (así padre->padre es gratis). Son 2 queries
     * fijas en total, en vez de varias por cada usuario al recalcular
     * jefaturas de un subárbol (UnidadOrganicaObserver, jefaturas:recalcular).
     * Las unidades son oficinas, un volumen chico.
     *
     * @return Collection<int, UnidadOrganica>
     */
    public static function arbolEnMemoria(): Collection
    {
        /** @var Collection<int, UnidadOrganica> $porId */
        $porId = static::query()->with(['jefe'])->get()->keyBy('id');

        foreach ($porId as $unidad) {
            $unidad->setRelation('padre', $unidad->parent_id !== null ? $porId->get($unidad->parent_id) : null);
        }

        return $porId;
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
        // Conjunto de visitados: si por edición directa en BD, un seeder o dos
        // admins a la vez llegara a existir un ciclo, el recorrido termina en
        // vez de colgar el request.
        $visitados = [$this->id => true];
        $pendientes = [$this->id];

        while ($pendientes) {
            $idActual = array_pop($pendientes);

            foreach ($porPadre->get($idActual, []) as $hijo) {
                if (isset($visitados[$hijo->id])) {
                    continue;
                }

                $visitados[$hijo->id] = true;
                $ids[] = $hijo->id;
                $pendientes[] = $hijo->id;
            }
        }

        return $ids;
    }
}
