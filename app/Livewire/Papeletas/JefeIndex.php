<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Bandeja del Jefe Inmediato / Jefe de Área, migrada de
 * App\Http\Controllers\Jefe\PapeletaController@index a Livewire puro.
 * Se re-renderiza sola en cuanto le llega una PapeletaNotification
 * (papeleta nueva por decidir, observación de RRHH, etc.) — ver
 * EscuchaNotificacionesEnVivo.
 *
 * Las acciones (aprobar/rechazar/observar/...) siguen siendo del
 * Controller original vía <form>; solo el listado necesitaba vivo.
 */
#[Layout('layouts.app')]
class JefeIndex extends Component
{
    use EscuchaNotificacionesEnVivo;

    public function render(): View
    {
        $user = Auth::user();

        $porDecidir = Papeleta::where('estado', PendienteJefe::class)
            ->where(function ($q) use ($user) {
                $q->where('jefe_inmediato_id', $user->id)
                    ->orWhere('jefe_area_id', $user->id);
            })
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $observacionesRrhh = Papeleta::where('estado', ObservadaPorRrhh::class)
            ->where('jefe_inmediato_id', $user->id)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $enCurso = Papeleta::where('estado', AutorizadaYCorriendo::class)
            ->where('jefe_inmediato_id', $user->id)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $sustentosPorRevisar = Papeleta::where('estado', RetornoPendienteSustento::class)
            ->where('jefe_inmediato_id', $user->id)
            ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
            ->with(['trabajador', 'motivo', 'sustentos'])
            ->latest()
            ->get();

        return view('livewire.papeletas.jefe-index', compact(
            'porDecidir',
            'observacionesRrhh',
            'enCurso',
            'sustentosPorRevisar',
        ));
    }
}
