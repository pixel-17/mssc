<?php

namespace App\Livewire\Reportes;

use App\Services\ResumenPapeletasService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Resumen mensual de papeletas por motivo y unidad, y tiempos de respuesta (solo RRHH y admin). */
#[Layout('layouts.app')]
#[Title('Resumen de papeletas')]
class ResumenPapeletasIndex extends Component
{
    public string $mes;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
    }

    public function render(ResumenPapeletasService $service): View
    {
        return view('livewire.reportes.resumen-papeletas-index', [
            'resumen' => $service->resumen($this->mes),
        ]);
    }
}
