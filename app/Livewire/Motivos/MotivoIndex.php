<?php

namespace App\Livewire\Motivos;

use App\Models\Motivo;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Listado del catálogo de Motivos en Blade + Livewire puro (reemplaza
 * a MotivoResource::table() de Filament). Ver App\Livewire\Motivos\MotivoForm
 * para crear/editar. Patrón calcado de App\Livewire\Sedes.
 */
#[Layout('layouts.app')]
class MotivoIndex extends Component
{
    public function eliminar(Motivo $motivo): void
    {
        $motivo->delete();

        session()->flash('mensaje', 'Motivo eliminado.');
    }

    public function render(): View
    {
        return view('livewire.motivos.motivo-index', [
            'motivos' => Motivo::orderBy('nombre')->get(),
        ]);
    }
}
