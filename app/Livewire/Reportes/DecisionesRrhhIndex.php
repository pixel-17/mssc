<?php

namespace App\Livewire\Reportes;

use App\Models\HistorialPapeleta;
use App\Models\User;
use App\Services\ReporteHorasAcumuladasService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bitácora de lo que RRHH hizo sobre las papeletas: cada aprobación,
 * rechazo, observación, revisión o corrección, con quién, cuándo y la
 * justificación. Sale del historial append-only de la papeleta, así que no
 * se puede editar. Solo RRHH y admin.
 */
#[Layout('layouts.app')]
#[Title('Decisiones de RR. HH.')]
class DecisionesRrhhIndex extends Component
{
    use WithPagination;

    public string $mes;

    public ?int $actorId = null;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
    }

    public function updated(string $nombre): void
    {
        if (in_array($nombre, ['mes', 'actorId'], true)) {
            $this->resetPage();
        }
    }

    public function render(ReporteHorasAcumuladasService $reporte): View
    {
        [$inicio, $fin] = $reporte->rangoDelMes($this->mes);

        $eventos = HistorialPapeleta::query()
            ->where('actor_tipo', 'rrhh')
            ->whereNotNull('actor_id')
            ->whereBetween('created_at', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->when($this->actorId, fn ($q) => $q->where('actor_id', $this->actorId))
            ->with(['actor', 'papeleta.trabajador'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.reportes.decisiones-rrhh-index', [
            'eventos' => $eventos,
            'actores' => User::role('rrhh')->orderBy('name')->get(['id', 'name', 'apellido']),
        ]);
    }
}
