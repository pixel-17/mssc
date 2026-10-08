<?php

namespace App\Livewire\Motivos;

use App\Models\Motivo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Component;

/**
 * Listado del catálogo de Motivos en Blade + Livewire puro. Ver App\Livewire\Motivos\MotivoForm
 * para crear/editar. Patrón calcado de App\Livewire\Sedes.
 */
#[Layout('layouts.app')]
#[Title('Motivos')]
class MotivoIndex extends Component
{
    use RequiereAdmin;

    public string $buscar = '';

    public function eliminar(Motivo $motivo): void
    {
        $this->autorizarAdmin();

        try {
            $motivo->delete();
            session()->now('mensaje', 'Motivo eliminado.');
        } catch (QueryException $e) {
            // FK (papeletas, usuarios, hijos...): no se pierde historial, se desactiva.
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            $motivo->forceFill(['activo' => false])->save();
            session()->now('mensaje', 'Motivo desactivado: tiene papeletas asociadas y no puede borrarse.');
        }
    }

    public function render(): View
    {
        return view('livewire.motivos.motivo-index', [
            'motivos' => Motivo::when(trim($this->buscar) !== '', fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', '%'.trim($this->buscar).'%')->orWhere('codigo', 'like', '%'.trim($this->buscar).'%')))->orderBy('nombre')->get(),
        ]);
    }
}
