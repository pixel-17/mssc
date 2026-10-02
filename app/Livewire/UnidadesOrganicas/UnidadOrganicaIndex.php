<?php

namespace App\Livewire\UnidadesOrganicas;

use App\Models\UnidadOrganica;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Component;

/**
 * Listado del catálogo de Unidades Orgánicas en Blade + Livewire puro. Ver
 * App\Livewire\UnidadesOrganicas\UnidadOrganicaForm para crear/editar.
 */
#[Layout('layouts.app')]
#[Title('Unidades orgánicas')]
class UnidadOrganicaIndex extends Component
{
    use RequiereAdmin;

    public string $buscar = '';

    public function eliminar(UnidadOrganica $unidad): void
    {
        $this->autorizarAdmin();

        try {
            $unidad->delete();
            session()->flash('mensaje', 'Unidad orgánica eliminada.');
        } catch (QueryException $e) {
            // FK (papeletas, usuarios, hijos...): no se pierde historial, se desactiva.
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            $unidad->forceFill(['activo' => false])->save();
            session()->flash('mensaje', 'Unidad desactivada: tiene usuarios o sub-unidades asociados y no puede borrarse.');
        }
    }

    public function render(): View
    {
        return view('livewire.unidades-organicas.unidad-organica-index', [
            'unidades' => UnidadOrganica::with(['padre', 'jefe', 'jefesTurno.jefe'])->when(trim($this->buscar) !== '', fn ($q) => $q->where('nombre', 'like', '%'.trim($this->buscar).'%'))->orderBy('nombre')->get(),
        ]);
    }
}
