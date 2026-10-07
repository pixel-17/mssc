<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Livewire\Concerns\PaginaListasDeBandeja;
use App\Models\Papeleta;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja de RRHH, migrada de
 * App\Http\Controllers\Rrhh\PapeletaController@index a Livewire puro.
 * Igual que JefeIndex: se re-renderiza sola vía
 * EscuchaNotificacionesEnVivo en cuanto llega una papeleta nueva
 * pendiente, un post-hoc, etc.
 */
#[Layout('layouts.app')]
#[Title('Bandeja de RRHH')]
class RrhhIndex extends Component
{
    use EscuchaNotificacionesEnVivo, PaginaListasDeBandeja, WithPagination;

    /** Filtro por nombre/apellido del trabajador, aplicado a las tres listas. */
    public string $buscar = '';

    /** @return array<int, string> */
    protected function nombresDePaginadores(): array
    {
        return ['porDecidirPage', 'posthocPage', 'sustentosPage'];
    }

    public function render(): View
    {
        // Filtro de nombre/apellido, reutilizado en las tres listas (mismo
        // criterio que UsuarioAdminIndex::render()).
        $filtroBuscar = fn ($query) => $query->whereHas('trabajador', fn ($q) => $q
            ->where('name', 'like', "%{$this->buscar}%")
            ->orWhere('apellido', 'like', "%{$this->buscar}%"));

        $porDecidir = $this->paginarLista(
            Papeleta::whereState('estado', PendienteRrhh::class)
                ->when($this->buscar, $filtroBuscar)
                ->with(['trabajador', 'motivo'])
                ->latest()
                ->orderByDesc('papeletas.id'),
            'porDecidirPage',
        );

        $posthocPendientes = $this->paginarLista(
            Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
                ->whereIn('revision_posthoc_estado', ['pendiente', 'respondida'])
                ->when($this->buscar, $filtroBuscar)
                ->with(['trabajador', 'motivo', 'resueltoPorJefe'])
                ->latest()
                ->orderByDesc('papeletas.id'),
            'posthocPage',
        );

        $sustentosPorRevisar = $this->paginarLista(
            Papeleta::whereState('estado', RetornoPendienteSustento::class)
                ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
                ->when($this->buscar, $filtroBuscar)
                // Solo el sustento "presentado" es relevante en esta bandeja
                // (la ficha, no este listado, es la que muestra el resto del
                // historial de sustentos de la papeleta).
                ->with(['trabajador', 'motivo', 'sustentos' => fn ($q) => $q->where('estado', 'presentado')])
                ->latest()
                ->orderByDesc('papeletas.id'),
            'sustentosPage',
        );

        return view('livewire.papeletas.rrhh-index', compact(
            'porDecidir',
            'posthocPendientes',
            'sustentosPorRevisar',
        ));
    }
}
