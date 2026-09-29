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
 * Bandeja del Jefe Inmediato, migrada de
 * App\Http\Controllers\Jefe\PapeletaController@index a Livewire puro.
 * Decidir es siempre responsabilidad del Jefe Inmediato: no hay
 * escalamiento a Jefe de Área, así que esta bandeja solo muestra lo
 * que el jefe autenticado puede decidir (deJefeInmediato).
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

    public string $buscar = '';

    public function render(): View
    {
        $user = Auth::user();

        // Buscador por trabajador (nombre, apellido o DNI): filtra todas las listas.
        $termino = trim($this->buscar);
        $filtroBuscar = fn ($query) => $query->whereHas('trabajador', fn ($q) => $q
            ->where(fn ($w) => $w
                ->where('name', 'like', "%{$termino}%")
                ->orWhere('apellido', 'like', "%{$termino}%")
                ->orWhere('dni', 'like', "%{$termino}%")));

        $porDecidir = Papeleta::whereState('estado', PendienteJefe::class)
            ->deJefeInmediato($user)
            ->when($termino !== '', $filtroBuscar)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        // Las que yo observé: esperan la respuesta del trabajador.
        $observadasPorMi = Papeleta::whereState('estado', ObservadaPorJefe::class)
            ->deJefeInmediato($user)
            ->when($termino !== '', $filtroBuscar)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $observacionesRrhh = Papeleta::whereState('estado', ObservadaPorRrhh::class)
            ->deJefeInmediato($user)
            ->when($termino !== '', $filtroBuscar)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        // Observaciones post-hoc de RRHH que solo yo (el que autorizó) puedo responder.
        $posthocPorResponder = Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
            ->where('revision_posthoc_estado', 'observada')
            ->where('resuelto_por_jefe_id', $user->id)
            ->when($termino !== '', $filtroBuscar)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $enCurso = Papeleta::whereState('estado', AutorizadaYCorriendo::class)
            ->deJefeInmediato($user)
            ->when($termino !== '', $filtroBuscar)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $sustentosPorRevisar = Papeleta::whereState('estado', RetornoPendienteSustento::class)
            ->deJefeInmediato($user)
            ->when($termino !== '', $filtroBuscar)
            ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
            ->with(['trabajador', 'motivo', 'sustentos'])
            ->latest()
            ->get();

        // Papeletas del turno vigente: tras aprobar, la papeleta sale de "Por decidir"
        // (pasa a RRHH, sigue en curso, se cierra...) pero el jefe debe seguir viéndola
        // hasta que termine el turno (fin_turno_at). Sin fin_turno_at (papeletas viejas)
        // se usa el día operativo de hoy. Se excluyen las que ya salen en otra lista.
        $yaMostradas = collect()
            ->merge($porDecidir)->merge($observadasPorMi)->merge($observacionesRrhh)->merge($posthocPorResponder)
            ->merge($enCurso)->merge($sustentosPorRevisar)
            ->pluck('id');

        $delTurno = Papeleta::deJefeInmediato($user)
            ->when($termino !== '', $filtroBuscar)
            ->whereNotIn('papeletas.id', $yaMostradas)
            ->where(fn ($q) => $q
                ->where('papeletas.fin_turno_at', '>', now())
                ->orWhere(fn ($q2) => $q2
                    ->whereNull('papeletas.fin_turno_at')
                    ->whereDate('papeletas.dia_operativo', today())))
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        return view('livewire.papeletas.jefe-index', compact(
            'porDecidir',
            'observadasPorMi',
            'observacionesRrhh',
            'posthocPorResponder',
            'enCurso',
            'sustentosPorRevisar',
            'delTurno',
        ));
    }
}
