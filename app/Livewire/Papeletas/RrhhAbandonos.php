<?php

namespace App\Livewire\Papeletas;

use App\Models\Papeleta;
use App\States\Papeleta\FinalizadoSinRetorno;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista de papeletas que terminaron como abandono (el trabajador salió y
 * nunca regresó): quién las marcó (un jefe, RRHH o el cierre automático),
 * cuándo y con qué justificación. Solo lectura: sirve para que RRHH revise
 * que cada abandono tenga un responsable y un motivo claros.
 */
#[Layout('layouts.app')]
#[Title('Abandonos')]
class RrhhAbandonos extends Component
{
    use WithPagination;

    public string $buscar = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $abandonos = Papeleta::whereState('estado', FinalizadoSinRetorno::class)
            ->where('causa_finalizacion_sin_retorno', 'abandono_no_marcado')
            ->when($this->buscar, fn ($q) => $q->whereHas('trabajador', fn ($t) => $t
                ->where('name', 'like', "%{$this->buscar}%")
                ->orWhere('apellido', 'like', "%{$this->buscar}%")))
            ->with([
                'trabajador',
                'motivo',
                'historial' => fn ($q) => $q->where('estado_nuevo', 'FinalizadoSinRetorno')->with('actor'),
            ])
            ->latest('dia_operativo')
            ->orderByDesc('papeletas.id')
            ->paginate(15);

        return view('livewire.papeletas.rrhh-abandonos', compact('abandonos'));
    }
}
