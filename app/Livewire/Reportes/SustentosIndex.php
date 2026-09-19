<?php

namespace App\Livewire\Reportes;

use App\Livewire\Concerns\RestringeAReportes;
use App\Models\User;
use App\Services\ReporteSustentosService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Qué sustentos (Paso 8, motivo Salud) están subiendo los trabajadores
 * y en qué estado está cada uno: pendiente de subir, presentado (por
 * revisar) o aprobado. Mismo criterio de acceso que el resto de
 * reportes — la frontera real vive en ReporteSustentosService::query().
 */
#[Layout('layouts.app')]
class SustentosIndex extends Component
{
    use RestringeAReportes, WithPagination;

    // #[Url] permite llegar aquí ya filtrado desde otros reportes
    // (p. ej. "Ver adjuntos" en el ranking de horas acumuladas).
    #[Url]
    public ?int $trabajadorId = null;

    /** Búsqueda libre por nombre, apellido o DNI. */
    #[Url]
    public string $buscar = '';

    #[Url]
    public string $estado = '';

    public ?string $desde = null;

    public ?string $hasta = null;

    public function mount(): void
    {
        $this->autorizarAccesoAReportes();
    }

    public function updatingTrabajadorId(): void
    {
        $this->resetPage();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    public function updatingDesde(): void
    {
        $this->resetPage();
    }

    public function updatingHasta(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['trabajadorId', 'buscar', 'estado', 'desde', 'hasta']);
        $this->resetPage();
    }

    public function render(ReporteSustentosService $service): View
    {
        /** @var User $user */
        $user = Auth::user();

        $filtros = [
            'trabajador_id' => $this->trabajadorId,
            'buscar' => $this->buscar,
            'estado' => $this->estado,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];

        $trabajadoresDisponibles = ($user->hasRole('admin') || $user->hasRole('rrhh'))
            ? User::role('trabajador')->orderBy('name')->get(['id', 'name', 'apellido'])
            : $user->trabajadoresParaReportes()->sortBy('name')->values();

        return view('livewire.reportes.sustentos-index', [
            'sustentos' => $service->query($user, $filtros)->paginate(20),
            'trabajadoresDisponibles' => $trabajadoresDisponibles,
        ]);
    }
}
