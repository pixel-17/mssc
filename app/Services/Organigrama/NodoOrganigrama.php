<?php

declare(strict_types=1);

namespace App\Services\Organigrama;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use LogicException;
use OutOfBoundsException;

/**
 * Un nodo del árbol del organigrama: la unidad, su(s) jefe(s), sus
 * trabajadores ya filtrados y sus sub-unidades (de nuevo
 * NodoOrganigrama, de forma recursiva).
 *
 * Es una clase -y no un array con un alias de tipo "NodoOrganigrama"- a
 * propósito: PHPStan no soporta tipos recursivos, ni como @phpstan-type
 * local ni como alias global en phpstan.neon ("Circular definition for
 * type alias NodoOrganigrama" al intentar expandirlo). Una clase, en
 * cambio, puede contener sin problema una colección de instancias de sí
 * misma: ahí ya no hay un alias que resolver, solo un grafo de objetos.
 *
 * Implementa ArrayAccess para que las vistas existentes
 * (_nodo.blade.php, organigrama-arbol.blade.php) sigan leyendo
 * $nodo['hijos'], $nodo['unidad'], etc. sin tener que reescribirlas.
 * Es de solo lectura: offsetSet()/offsetUnset() lanzan, igual que
 * pasaba (en los hechos) con el array inmutable que devolvía antes
 * construirNodo().
 *
 * @implements \ArrayAccess<string, mixed>
 */
final class NodoOrganigrama implements \ArrayAccess
{
    private const CLAVES = [
        'unidad', 'jefes', 'jefes_en_filtro', 'filtro_sede',
        'miembros', 'hijos', 'total', 'sin_jefe',
    ];

    /**
     * @param  Collection<int, User>  $jefes
     * @param  array<int, int>  $jefesEnFiltro
     * @param  EloquentCollection<int, User>  $miembros
     * @param  Collection<int, NodoOrganigrama>  $hijos
     */
    public function __construct(
        public readonly UnidadOrganica $unidad,
        public readonly Collection $jefes,
        public readonly array $jefesEnFiltro,
        public readonly bool $filtroSede,
        public readonly EloquentCollection $miembros,
        public readonly Collection $hijos,
        public readonly int|float $total,
        public readonly bool $sinJefe,
    ) {}

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, self::CLAVES, true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'unidad' => $this->unidad,
            'jefes' => $this->jefes,
            'jefes_en_filtro' => $this->jefesEnFiltro,
            'filtro_sede' => $this->filtroSede,
            'miembros' => $this->miembros,
            'hijos' => $this->hijos,
            'total' => $this->total,
            'sin_jefe' => $this->sinJefe,
            default => throw new OutOfBoundsException("Clave desconocida en NodoOrganigrama: {$offset}"),
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('NodoOrganigrama es de solo lectura.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('NodoOrganigrama es de solo lectura.');
    }
}
