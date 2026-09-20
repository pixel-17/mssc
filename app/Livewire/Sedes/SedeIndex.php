<?php

namespace App\Livewire\Sedes;

use App\Models\Sede;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Component;

/**
 * Listado del catálogo de Sedes en Blade + Livewire puro (reemplaza a
 * SedeResource::table() de Filament). Ver App\Livewire\Sedes\SedeForm
 * para crear/editar.
 */
#[Layout('layouts.app')]
#[Title('Sedes')]
class SedeIndex extends Component
{
    use RequiereAdmin;

    public function eliminar(Sede $sede): void
    {
        $this->autorizarAdmin();

        try {
            $sede->delete();
            session()->flash('mensaje', 'Sede eliminada.');
        } catch (QueryException $e) {
            // FK (papeletas, usuarios, hijos...): no se pierde historial, se desactiva.
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            $sede->forceFill(['activo' => false])->save();
            session()->flash('mensaje', 'Sede desactivada: tiene usuarios o papeletas asociados y no puede borrarse.');
        }
    }

    public function render(): View
    {
        return view('livewire.sedes.sede-index', [
            'sedes' => Sede::withCount('usuarios')->orderBy('nombre')->get(),
        ]);
    }
}
