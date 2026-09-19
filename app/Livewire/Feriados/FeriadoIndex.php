<?php

namespace App\Livewire\Feriados;

use App\Models\Feriado;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Component;

/**
 * Listado del catálogo de Feriados en Blade + Livewire puro (reemplaza
 * a FeriadoResource::table() de Filament). Ver App\Livewire\Feriados\FeriadoForm
 * para crear/editar.
 */
#[Layout('layouts.app')]
class FeriadoIndex extends Component
{
    use RequiereAdmin;

    public function eliminar(Feriado $feriado): void
    {
        $this->autorizarAdmin();

        $feriado->delete();

        session()->flash('mensaje', 'Feriado eliminado.');
    }

    public function render(): View
    {
        return view('livewire.feriados.feriado-index', [
            'feriados' => Feriado::orderByDesc('fecha')->get(),
        ]);
    }
}
