<?php

namespace App\Livewire\Motivos;

use App\Models\Motivo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Component;

/**
 * Listado del catálogo de Motivos en Blade + Livewire puro (reemplaza
 * a MotivoResource::table() de Filament). Ver App\Livewire\Motivos\MotivoForm
 * para crear/editar. Patrón calcado de App\Livewire\Sedes.
 */
#[Layout('layouts.app')]
class MotivoIndex extends Component
{
    use RequiereAdmin;

    public function eliminar(Motivo $motivo): void
    {
        $this->autorizarAdmin();

        try {
            $motivo->delete();
            session()->flash('mensaje', 'Motivo eliminado.');
        } catch (QueryException $e) {
            // FK (papeletas, usuarios, hijos...): no se pierde historial, se desactiva.
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            $motivo->forceFill(['activo' => false])->save();
            session()->flash('mensaje', 'Motivo desactivado: tiene papeletas asociadas y no puede borrarse.');
        }
    }

    public function render(): View
    {
        return view('livewire.motivos.motivo-index', [
            'motivos' => Motivo::orderBy('nombre')->get(),
        ]);
    }
}
