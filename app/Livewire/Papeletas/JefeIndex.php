<?php

namespace App\Livewire\Papeletas;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Livewire\Concerns\PaginaListasDeBandeja;
use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

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
    use EscuchaNotificacionesEnVivo, PaginaListasDeBandeja, WithPagination;

    public string $buscar = '';

    /** @return array<int, string> */
    protected function nombresDePaginadores(): array
    {
        return [
            'porDecidirPage',
            'observadasPorMiPage',
            'observacionesRrhhPage',
            'posthocPorResponderPage',
            'enCursoPage',
            'delTurnoPage',
        ];
    }

    public function render(): View
    {
        $user = Auth::user();

        // Solo jefes de equipo: el admin no tiene bandeja de jefe ni por URL.
        abort_unless($user->esJefeDeEquipo(), 403);

        // Buscador por trabajador (nombre, apellido o DNI): filtra todas las listas.
        $termino = trim($this->buscar);
        $filtroBuscar = fn ($query) => $query->whereHas('trabajador', fn ($q) => $q
            ->where(fn ($w) => $w
                ->where('name', 'like', "%{$termino}%")
                ->orWhere('apellido', 'like', "%{$termino}%")
                ->orWhere('dni', 'like', "%{$termino}%")));

        // Consultas base de cada lista (sin búsqueda, relaciones ni orden).
        // Sirven para paginar y también para que "Papeletas del turno"
        // excluya TODO lo que ya sale en otra lista, no solo lo de la
        // página que se está viendo.
        $bases = [
            'porDecidir' => fn () => Papeleta::whereState('estado', PendienteJefe::class)
                ->deJefeInmediato($user),
            // Las que yo observé: esperan la respuesta del trabajador.
            'observadasPorMi' => fn () => Papeleta::whereState('estado', ObservadaPorJefe::class)
                ->deJefeInmediato($user),
            'observacionesRrhh' => fn () => Papeleta::whereState('estado', ObservadaPorRrhh::class)
                ->deJefeInmediato($user),
            // Observaciones post-hoc de RRHH que solo yo (el que autorizó) puedo responder.
            'posthocPorResponder' => fn () => Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
                ->where('revision_posthoc_estado', 'observada')
                ->where('resuelto_por_jefe_id', $user->id),
            'enCurso' => fn () => Papeleta::whereState('estado', AutorizadaYCorriendo::class)
                ->deJefeInmediato($user),
        ];

        $listar = fn ($consulta, string $nombre, array $relaciones = ['trabajador', 'motivo']) => $this->paginarLista(
            $consulta
                ->when($termino !== '', $filtroBuscar)
                ->with($relaciones)
                ->latest()
                ->orderByDesc('papeletas.id'),
            $nombre.'Page',
        );

        $porDecidir = $listar($bases['porDecidir'](), 'porDecidir');
        $observadasPorMi = $listar($bases['observadasPorMi'](), 'observadasPorMi');
        $observacionesRrhh = $listar($bases['observacionesRrhh'](), 'observacionesRrhh');
        $posthocPorResponder = $listar($bases['posthocPorResponder'](), 'posthocPorResponder');
        $enCurso = $listar($bases['enCurso'](), 'enCurso');

        // Papeletas del turno vigente: tras aprobar, la papeleta sale de "Por decidir"
        // (pasa a RRHH, sigue en curso, se cierra...) pero el jefe debe seguir viéndola
        // hasta que termine el turno (fin_turno_at). Sin fin_turno_at (papeletas viejas)
        // se usa el día operativo de hoy. Se excluyen las que ya salen en otra lista.
        $delTurnoConsulta = Papeleta::deJefeInmediato($user)->delTurnoVigente();

        foreach ($bases as $base) {
            $delTurnoConsulta->whereNotIn('papeletas.id', $base()->select('papeletas.id'));
        }

        $delTurno = $listar($delTurnoConsulta, 'delTurno');

        return view('livewire.papeletas.jefe-index', compact(
            'porDecidir',
            'observadasPorMi',
            'observacionesRrhh',
            'posthocPorResponder',
            'enCurso',
            'delTurno',
        ));
    }
}
