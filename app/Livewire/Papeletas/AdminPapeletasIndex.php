<?php

namespace App\Livewire\Papeletas;

use App\Models\Papeleta;
use App\Models\User;
use App\Services\ReporteHorasAcumuladasService;
use App\States\Papeleta\PapeletaState;
use App\Support\PapeletaEstadoPresentacion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de papeletas para el admin y RR. HH., SOLO LECTURA.
 *
 * Tres modos, para no cargar nunca el histórico completo:
 * - Por día (por defecto): las papeletas de UN día (hoy o el que se elija).
 * - Por mes: todas las papeletas de un mes completo (Y-m).
 * - Por trabajador: todas sus papeletas, de todas las fechas y estados.
 *   Se elige el trabajador buscándolo por nombre, apellido o DNI.
 */
#[Layout('layouts.app')]
#[Title('Papeletas')]
class AdminPapeletasIndex extends Component
{
    use WithPagination;

    /** Búsqueda de trabajador (nombre, apellido o DNI). */
    public string $buscar = '';

    public string $estado = '';

    /** 'dia' | 'mes'. Solo aplica cuando no se está viendo el historial de un trabajador. */
    public string $vista = 'dia';

    public string $fecha = '';

    /** Formato Y-m, usado cuando $vista === 'mes'. */
    public string $mes = '';

    /** Si está definido, se muestra el historial completo de ese trabajador. */
    #[Url(as: 'trabajador')]
    public ?int $trabajadorId = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
        $this->mes = now()->format('Y-m');
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function updatedFecha(): void
    {
        $this->resetPage();
    }

    public function updatedMes(): void
    {
        $this->resetPage();
    }

    /** Cambia entre ver por día y ver por mes (no afecta el historial de un trabajador). */
    public function cambiarVista(string $vista): void
    {
        if (! in_array($vista, ['dia', 'mes'], true)) {
            return;
        }

        $this->vista = $vista;
        $this->resetPage();
    }

    public function verHistorial(int $trabajadorId): void
    {
        $this->trabajadorId = $trabajadorId;
        $this->estado = '';
        $this->buscar = '';
        $this->resetPage();
    }

    public function limpiarBusqueda(): void
    {
        $this->buscar = '';
        $this->resetPage();
    }

    public function volverAlDia(): void
    {
        $this->trabajadorId = null;
        $this->estado = '';
        $this->buscar = '';
        $this->vista = 'dia';
        $this->fecha = now()->toDateString();
        $this->resetPage();
    }

    /** @return array<string, string> clase de estado => etiqueta */
    public static function estados(): array
    {
        return collect(glob(app_path('States/Papeleta/*.php')))
            ->map(fn (string $archivo) => 'App\\States\\Papeleta\\'.pathinfo($archivo, PATHINFO_FILENAME))
            ->reject(fn (string $clase) => $clase === PapeletaState::class || ! is_subclass_of($clase, PapeletaState::class))
            ->mapWithKeys(fn (string $clase) => [$clase => PapeletaEstadoPresentacion::para($clase)[0]])
            ->sort()
            ->all();
    }

    public function render(ReporteHorasAcumuladasService $reportes): View
    {
        $estados = self::estados();
        $termino = trim($this->buscar);
        $trabajador = $this->trabajadorId ? User::find($this->trabajadorId) : null;

        $papeletas = null;
        $coincidencias = collect();
        $inicioMes = null;
        $finMes = null;

        if ($trabajador) {
            // Historial completo de UNA persona: todas las fechas y estados.
            $papeletas = Papeleta::query()
                ->where('trabajador_id', $trabajador->id)
                ->when(array_key_exists($this->estado, $estados), fn ($q) => $q->whereState('estado', $this->estado))
                ->with(['motivo'])
                ->latest('dia_operativo')
                ->orderByDesc('papeletas.id')
                ->paginate(20);
        } elseif ($this->vista === 'mes') {
            // Mismo cálculo de rango que el reporte de horas acumuladas:
            // un mes mal formado (Y-m) se degrada al mes actual.
            [$inicioMes, $finMes] = $reportes->rangoDelMes($this->mes);

            $papeletas = Papeleta::query()
                ->whereBetween('dia_operativo', [$inicioMes->toDateString(), $finMes->toDateString()])
                ->when(array_key_exists($this->estado, $estados), fn ($q) => $q->whereState('estado', $this->estado))
                ->when($termino !== '', fn ($q) => $q->whereHas('trabajador', fn ($t) => $t
                    ->where('name', 'like', "%{$termino}%")
                    ->orWhere('apellido', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%")))
                ->with(['trabajador', 'motivo'])
                ->latest('dia_operativo')
                ->orderByDesc('papeletas.id')
                ->paginate(20);

            $coincidencias = $this->coincidencias($termino);
        } else {
            $fecha = $this->fecha;
            try {
                $fecha = Carbon::parse($this->fecha)->toDateString();
            } catch (\Throwable) {
                $fecha = now()->toDateString();
            }

            $papeletas = Papeleta::query()
                ->whereDate('dia_operativo', $fecha)
                ->when(array_key_exists($this->estado, $estados), fn ($q) => $q->whereState('estado', $this->estado))
                ->when($termino !== '', fn ($q) => $q->whereHas('trabajador', fn ($t) => $t
                    ->where('name', 'like', "%{$termino}%")
                    ->orWhere('apellido', 'like', "%{$termino}%")
                    ->orWhere('dni', 'like', "%{$termino}%")))
                ->with(['trabajador', 'motivo'])
                ->latest('dia_operativo')
                ->orderByDesc('papeletas.id')
                ->paginate(20);

            $coincidencias = $this->coincidencias($termino);
        }

        return view('livewire.papeletas.admin-papeletas-index', [
            'papeletas' => $papeletas,
            'estados' => $estados,
            'trabajador' => $trabajador,
            'coincidencias' => $coincidencias,
            'inicioMes' => $inicioMes,
            'finMes' => $finMes,
        ]);
    }

    /** Para encontrar a alguien (por día o por mes) y abrir su historial completo. */
    private function coincidencias(string $termino): Collection
    {
        if ($termino === '') {
            return collect();
        }

        return User::query()
            ->where(fn ($q) => $q
                ->where('name', 'like', "%{$termino}%")
                ->orWhere('apellido', 'like', "%{$termino}%")
                ->orWhere('dni', 'like', "%{$termino}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'apellido', 'dni']);
    }
}
