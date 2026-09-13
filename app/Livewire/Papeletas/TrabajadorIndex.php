<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja del Trabajador (Paso 1 del flujo), migrada de
 * App\Http\Controllers\Trabajador\PapeletaController@index a Livewire
 * puro para que se actualice sola en tiempo real: en cuanto su Jefe o
 * RRHH deciden algo, NotificarPapeletaService le manda una
 * PapeletaNotification (canal 'broadcast') y este listado se
 * re-renderiza al instante sin que el trabajador recargue la página.
 *
 * crear/store/show/cancelar siguen siendo del Controller original —
 * solo el listado (index) necesitaba tiempo real.
 */
#[Layout('components.trabajador-layout')]
class TrabajadorIndex extends Component
{
    use EscuchaNotificacionesEnVivo, WithPagination;

    public function render(): View
    {
        return view('livewire.papeletas.trabajador-index', [
            'papeletas' => Auth::user()->papeletas()
                ->with(['motivo', 'sede', 'retorno'])
                ->latest()
                ->paginate(15),
        ]);
    }
}
