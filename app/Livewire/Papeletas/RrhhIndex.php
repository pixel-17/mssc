<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Models\Papeleta;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Bandeja de RRHH, migrada de
 * App\Http\Controllers\Rrhh\PapeletaController@index a Livewire puro.
 * Igual que JefeIndex: se re-renderiza sola vía
 * EscuchaNotificacionesEnVivo en cuanto llega una papeleta nueva
 * pendiente, un post-hoc, etc.
 */
#[Layout('layouts.app')]
class RrhhIndex extends Component
{
    use EscuchaNotificacionesEnVivo;

    public function render(): View
    {
        $porDecidir = Papeleta::where('estado', PendienteRrhh::class)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $posthocPendientes = Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
            ->where('revision_posthoc_estado', 'pendiente')
            ->with(['trabajador', 'motivo', 'resueltoPorJefe'])
            ->latest()
            ->get();

        $sustentosPorRevisar = Papeleta::where('estado', RetornoPendienteSustento::class)
            ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
            ->with(['trabajador', 'motivo', 'sustentos'])
            ->latest()
            ->get();

        return view('livewire.papeletas.rrhh-index', compact(
            'porDecidir',
            'posthocPendientes',
            'sustentosPorRevisar',
        ));
    }
}
