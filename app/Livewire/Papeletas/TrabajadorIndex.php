<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
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
#[Title('Mis papeletas')]
class TrabajadorIndex extends Component
{
    use EscuchaNotificacionesEnVivo, WithPagination;

    public string $buscar = '';

    /**
     * false (por defecto): solo las papeletas del turno en curso; salen de
     * esta vista cuando el turno termina. true: todas las papeletas del usuario.
     */
    #[Url(as: 'todas')]
    public bool $verTodas = false;

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedVerTodas(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $termino = trim($this->buscar);
        $usuario = Auth::user();

        $papeletas = $usuario->papeletas()
            ->with(['motivo', 'sede', 'retorno'])
            ->when(! $this->verTodas, fn ($q) => $q->delTurnoVigente())
            ->when($termino !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('motivo', fn ($m) => $m->where('nombre', 'like', "%{$termino}%"))
                ->orWhere('justificacion', 'like', "%{$termino}%")))
            ->latest()
            ->orderByDesc('papeletas.id')
            ->paginate(15);

        // Distingue "no hay nada en mi turno" de "nunca he creado una papeleta".
        $tieneAlguna = ! $papeletas->isEmpty() || $this->verTodas || $termino !== ''
            || $usuario->papeletas()->exists();

        return view('livewire.papeletas.trabajador-index', [
            'papeletas' => $papeletas,
            'tieneAlguna' => $tieneAlguna,
        ]);
    }
}
