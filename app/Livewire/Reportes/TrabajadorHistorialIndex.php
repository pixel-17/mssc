<?php

namespace App\Livewire\Reportes;

use App\Livewire\Concerns\RestringeAReportes;
use App\Exports\HistorialTrabajadorExport;
use App\Models\User;
use App\Services\HistorialTrabajadorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Buscador + ficha de un trabajador: todo su historial de papeletas
 * (sin recortar por mes) y sus totales de siempre. Complementa a
 * HorasAcumuladasIndex (todos, un mes) mirando el caso opuesto: uno
 * solo, todo el tiempo — para revisar un caso puntual a fondo.
 */
#[Layout('layouts.app')]
#[Title('Historial por trabajador')]
class TrabajadorHistorialIndex extends Component
{
    use RestringeAReportes;

    public string $buscar = '';

    // #[Url]: permite abrir la ficha ya con el trabajador elegido desde
    // otros reportes. render() valida puedeVer() antes de mostrar nada.
    #[Url]
    public ?int $trabajadorId = null;

    public function mount(): void
    {
        $this->autorizarAccesoAReportes();
    }

    public function elegir(int $trabajadorId): void
    {
        $this->trabajadorId = $trabajadorId;
        $this->buscar = '';
    }

    public function quitar(): void
    {
        $this->trabajadorId = null;
    }

    /** Descarga en Excel el historial completo del trabajador elegido (mismo alcance que la pantalla). */
    public function exportar(HistorialTrabajadorService $service)
    {
        /** @var User $user */
        $user = Auth::user();

        $trabajador = $this->trabajadorId ? User::find($this->trabajadorId) : null;

        abort_unless($trabajador && $service->puedeVer($user, $trabajador), 403);

        return Excel::download(
            new HistorialTrabajadorExport($service->historial($trabajador)),
            'historial-papeletas-'.\Illuminate\Support\Str::slug($trabajador->nombre_completo).'.xlsx',
        );
    }

    public function render(HistorialTrabajadorService $service): View
    {
        /** @var User $user */
        $user = Auth::user();

        $trabajador = null;
        $historial = collect();
        $resumen = null;

        if ($this->trabajadorId) {
            $candidato = User::find($this->trabajadorId);

            if ($candidato && $service->puedeVer($user, $candidato)) {
                $trabajador = $candidato;
                $historial = $service->historial($trabajador);
                $resumen = $service->resumen($historial);
            } else {
                $this->trabajadorId = null;
            }
        }

        return view('livewire.reportes.trabajador-historial-index', [
            'sugerencias' => $trabajador ? collect() : $service->trabajadoresVisibles($user, $this->buscar),
            'trabajador' => $trabajador,
            'historial' => $historial,
            'resumen' => $resumen,
        ]);
    }
}
