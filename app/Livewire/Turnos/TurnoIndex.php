<?php

namespace App\Livewire\Turnos;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado del catálogo de Turnos en Blade + Livewire puro (reemplaza
 * a TurnoResource::table() de Filament). Ver App\Livewire\Turnos\TurnoForm
 * para crear/editar. Patrón calcado de App\Livewire\Sedes.
 */
#[Layout('layouts.app')]
class TurnoIndex extends Component
{
    use WithPagination;

    public ?int $userId = null;

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function eliminar(Turno $turno): void
    {
        $turno->delete();

        session()->flash('mensaje', 'Turno eliminado.');
    }

    public function render(): View
    {
        return view('livewire.turnos.turno-index', [
            'turnos' => Turno::with(['usuario', 'sede'])
                ->when($this->userId, fn ($query) => $query->where('user_id', $this->userId))
                ->orderByDesc('fecha')
                ->paginate(20),
            'trabajadores' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
