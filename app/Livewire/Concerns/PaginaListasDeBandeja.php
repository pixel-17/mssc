<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Paginación para bandejas con VARIAS listas en la misma pantalla
 * (Jefe, RRHH). Cada lista usa su propio paginador con nombre, así que
 * pasar de página en una no mueve las demás.
 *
 * El componente que lo use debe declarar también `use WithPagination`
 * (de Livewire) y definir nombresDePaginadores().
 */
trait PaginaListasDeBandeja
{
    /** Filas por página en cada lista de la bandeja. */
    protected int $porPaginaBandeja = 10;

    /**
     * Nombres de los paginadores (uno por lista), p. ej. 'porDecidirPage'.
     *
     * @return array<int, string>
     */
    abstract protected function nombresDePaginadores(): array;

    /** Al cambiar la búsqueda todas las listas vuelven a la página 1. */
    public function updatedBuscar(): void
    {
        foreach ($this->nombresDePaginadores() as $nombre) {
            $this->resetPage($nombre);
        }
    }

    /**
     * Pagina una lista. Si la página pedida ya no existe (p. ej. se
     * aprobó la última papeleta de la página 2), vuelve a la última
     * página válida en vez de mostrar la lista vacía.
     */
    protected function paginarLista(Builder $consulta, string $nombre): LengthAwarePaginator
    {
        $lista = (clone $consulta)->paginate($this->porPaginaBandeja, ['*'], $nombre);

        if ($lista->currentPage() > $lista->lastPage()) {
            $ultima = $lista->lastPage();

            $this->setPage($ultima, $nombre);

            $lista = $consulta->paginate($this->porPaginaBandeja, ['*'], $nombre, $ultima);
        }

        return $lista;
    }
}
