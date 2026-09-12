<?php

namespace App\Livewire\UnidadesOrganicas;

use App\Models\UnidadOrganica;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Listado del catálogo de Unidades Orgánicas en Blade + Livewire puro
 * (reemplaza a UnidadOrganicaResource::table() de Filament). Ver
 * App\Livewire\UnidadesOrganicas\UnidadOrganicaForm para crear/editar.
 */
#[Layout('layouts.app')]
class UnidadOrganicaIndex extends Component
{
    public function eliminar(UnidadOrganica $unidad): void
    {
        $unidad->delete();

        session()->flash('mensaje', 'Unidad orgánica eliminada.');
    }

    public function render(): View
    {
        return view('livewire.unidades-organicas.unidad-organica-index', [
            'unidades' => UnidadOrganica::with(['padre', 'jefe'])->orderBy('nombre')->get(),
        ]);
    }
}
