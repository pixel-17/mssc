<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
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
#[Title('Bandeja de Jefe')]
class JefeIndex extends Component
{
    use EscuchaNotificacionesEnVivo;

    public function render(): View
    {
        $user = Auth::user();

        $porDecidir = Papeleta::whereState('estado', PendienteJefe::class)
            ->where(fn ($q) => $q->deJefeInmediato($user)->orWhere('papeletas.jefe_area_id', $user->id))
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        // Las que yo observé: esperan la respuesta del trabajador.
        $observadasPorMi = Papeleta::whereState('estado', ObservadaPorJefe::class)
            ->where(fn ($q) => $q->deJefeInmediato($user)->orWhere('papeletas.jefe_area_id', $user->id))
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $observacionesRrhh = Papeleta::whereState('estado', ObservadaPorRrhh::class)
            ->deJefeInmediato($user)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $enCurso = Papeleta::whereState('estado', AutorizadaYCorriendo::class)
            ->deJefeInmediato($user)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $sustentosPorRevisar = Papeleta::whereState('estado', RetornoPendienteSustento::class)
            ->deJefeInmediato($user)
            ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
            ->with(['trabajador', 'motivo', 'sustentos'])
            ->latest()
            ->get();

        return view('livewire.papeletas.jefe-index', compact(
            'porDecidir',
            'observadasPorMi',
            'observacionesRrhh',
            'enCurso',
            'sustentosPorRevisar',
        ));
    }
}
