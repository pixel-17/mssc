<?php

namespace App\Livewire\Turnos;

use App\Models\Configuracion;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de equipo (grilla): filas = trabajadores que este Jefe
 * (Inmediato o de Área) supervisa, columnas = días del mes, celdas =
 * M/T/N/D (ver Turno::etiqueta). El Jefe puede crear/editar el
 * horario de cada trabajador desde aquí (enlaza a
 * turnos.configuracion, ya existente). Admin y Trabajador NO usan
 * esta pantalla — ellos ven la vista individual (CalendarioIndividualIndex).
 *
 * Alcance del Jefe de Área: su unidad + TODAS las sub-unidades debajo
 * de ella (mismo criterio ya usado en UsuarioController::index /
 * CrearUsuarioRequest::unidadesDisponibles, para no inventar un
 * segundo criterio de alcance distinto en la misma pantalla de
 * jefatura).
 */
#[Layout('layouts.app')]
class CalendarioEquipoIndex extends Component
{
    public int $anio;

    public int $mes;

    public function mount(): void
    {
        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    public function mesAnterior(): void
    {
        $fecha = Carbon::create($this->anio, $this->mes, 1)->subMonthNoOverflow();
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
    }

    public function mesSiguiente(): void
    {
        $fecha = Carbon::create($this->anio, $this->mes, 1)->addMonthNoOverflow();
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
    }

    public function irAHoy(): void
    {
        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    /**
     * IDs de todas las unidades (encabezadas + sub-unidades) donde el
     * usuario autenticado es Jefe de Área. Vacío si solo es Jefe
     * Inmediato. Calcado de UsuarioController::subtreeIdsDeAreasQueEncabeza.
     */
    private function subtreeIdsDeAreasQueEncabeza(User $user): Collection
    {
        $ids = collect();

        foreach ($user->unidadesQueEncabeza as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }

    public function render(): View
    {
        $user = auth()->user();
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);
        $esJefeDeArea = $unidadIds->isNotEmpty();

        $trabajadores = $esJefeDeArea
            ? User::whereIn('unidad_organica_id', $unidadIds)->orderBy('name')->get()
            : $user->trabajadoresComoJefeInmediato()
                ->push($user)
                ->unique('id')
                ->sortBy('name')
                ->values();

        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();
        $dias = collect(range(1, $finMes->day));

        $turnosPorUsuario = Turno::whereIn('user_id', $trabajadores->pluck('id'))
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->with('sede')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($turnos) => $turnos->keyBy(fn (Turno $turno) => $turno->fecha->day));

        return view('livewire.turnos.calendario-equipo-index', [
            'trabajadores' => $trabajadores,
            'esJefeDeArea' => $esJefeDeArea,
            'dias' => $dias,
            'inicioMes' => $inicioMes,
            'turnosPorUsuario' => $turnosPorUsuario,
            'modoEstricto728Activo' => Configuracion::valorDe('MODO_ESTRICTO_728', '0') === '1',
            'hoy' => now(),
        ]);
    }
}
