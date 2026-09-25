<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Models\Papeleta;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

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
    use EscuchaNotificacionesEnVivo;

    /** Filtro por nombre/apellido del trabajador, aplicado a las tres listas. */
    public string $buscar = '';

    public function render(): View
    {
        // Filtro de nombre/apellido, reutilizado en las tres listas (mismo
        // criterio que UsuarioAdminIndex::render()).
        $filtroBuscar = fn ($query) => $query->whereHas('trabajador', fn ($q) => $q
            ->where('name', 'like', "%{$this->buscar}%")
            ->orWhere('apellido', 'like', "%{$this->buscar}%"));

        $porDecidir = Papeleta::whereState('estado', PendienteRrhh::class)
            ->when($this->buscar, $filtroBuscar)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $posthocPendientes = Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
            ->where('revision_posthoc_estado', 'pendiente')
            ->when($this->buscar, $filtroBuscar)
            ->with(['trabajador', 'motivo', 'resueltoPorJefe'])
            ->latest()
            ->get();

        $sustentosPorRevisar = Papeleta::whereState('estado', RetornoPendienteSustento::class)
            ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
            ->when($this->buscar, $filtroBuscar)
            // Solo el sustento "presentado" es relevante en esta bandeja
            // (la ficha, no este listado, es la que muestra el resto del
            // historial de sustentos de la papeleta).
            ->with(['trabajador', 'motivo', 'sustentos' => fn ($q) => $q->where('estado', 'presentado')])
            ->latest()
            ->get();

        return view('livewire.papeletas.rrhh-index', compact(
            'porDecidir',
            'posthocPendientes',
            'sustentosPorRevisar',
        ));
    }
}
