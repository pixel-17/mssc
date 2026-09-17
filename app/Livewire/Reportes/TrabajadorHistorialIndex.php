<?php

namespace App\Livewire\Reportes;

use App\Livewire\Concerns\RestringeAReportes;
use App\Models\User;
use App\Services\HistorialTrabajadorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Buscador + ficha de un trabajador: todo su historial de papeletas
 * (sin recortar por mes) y sus totales de siempre. Complementa a
 * HorasAcumuladasIndex (todos, un mes) mirando el caso opuesto: uno
 * solo, todo el tiempo — para revisar un caso puntual a fondo.
 */
#[Layout('layouts.app')]
class TrabajadorHistorialIndex extends Component
{
    use RestringeAReportes;

    public string $buscar = '';

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
