<?php

namespace App\Livewire\Sedes;

use App\Models\Sede;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Listado del catálogo de Sedes en Blade + Livewire puro (reemplaza a
 * SedeResource::table() de Filament). Ver App\Livewire\Sedes\SedeForm
 * para crear/editar.
 */
#[Layout('layouts.app')]
class SedeIndex extends Component
{
    public function eliminar(Sede $sede): void
    {
        $sede->delete();

        session()->flash('mensaje', 'Sede eliminada.');
    }

    public function render(): View
    {
        return view('livewire.sedes.sede-index', [
            'sedes' => Sede::withCount('usuarios')->orderBy('nombre')->get(),
        ]);
    }
}
