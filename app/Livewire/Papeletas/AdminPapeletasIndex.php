<?php

namespace App\Livewire\Papeletas;

use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\PapeletaState;
use App\Support\PapeletaEstadoPresentacion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de papeletas para el admin, SOLO LECTURA.
 *
 * Dos modos, para no cargar nunca el histórico completo:
 * - Por defecto: las papeletas de UN día (hoy o el que se elija).
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

    public string $fecha = '';

    /** Si está definido, se muestra el historial completo de ese trabajador. */
    #[Url(as: 'trabajador')]
    public ?int $trabajadorId = null;

    public function mount(): void
    {
        $this->fecha = now()->toDateString();
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

    public function render(): View
    {
        $estados = self::estados();
        $termino = trim($this->buscar);
        $trabajador = $this->trabajadorId ? User::find($this->trabajadorId) : null;

        $papeletas = null;
        $coincidencias = collect();

        if ($trabajador) {
            // Historial completo de UNA persona: todas las fechas y estados.
            $papeletas = Papeleta::query()
                ->where('trabajador_id', $trabajador->id)
                ->when(array_key_exists($this->estado, $estados), fn ($q) => $q->whereState('estado', $this->estado))
                ->with(['motivo'])
                ->latest('dia_operativo')
                ->orderByDesc('papeletas.id')
                ->paginate(20);
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

            // Para encontrar a alguien y abrir su historial completo.
            if ($termino !== '') {
                $coincidencias = User::query()
                    ->where(fn ($q) => $q
                        ->where('name', 'like', "%{$termino}%")
                        ->orWhere('apellido', 'like', "%{$termino}%")
                        ->orWhere('dni', 'like', "%{$termino}%"))
                    ->orderBy('name')
                    ->limit(10)
                    ->get(['id', 'name', 'apellido', 'dni']);
            }
        }

        return view('livewire.papeletas.admin-papeletas-index', [
            'papeletas' => $papeletas,
            'estados' => $estados,
            'trabajador' => $trabajador,
            'coincidencias' => $coincidencias,
        ]);
    }
}
