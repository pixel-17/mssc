<?php

namespace App\Livewire\Papeletas;

use App\Models\Papeleta;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista informativa de papeletas cerradas por abandono (el trabajador salió y
 * nunca regresó): quién las marcó (un jefe, RRHH o el cierre automático),
 * cuándo y con qué justificación. Solo lectura: sirve para que RRHH revise
 * que cada abandono tenga un responsable y un motivo claros.
 */
#[Layout('layouts.app')]
#[Title('Abandonos')]
class RrhhAbandonos extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $causa = '';

    /** Etiquetas de causa_finalizacion_sin_retorno (enum de la BD). */
    public const CAUSAS = [
        'abandono_no_marcado' => 'Abandono no marcado',
        'comision_servicio_campo' => 'Comisión de servicio en campo',
    ];

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedCausa(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        // Cualquier estado: Cerrada (justificado), Finalizada (sin justificar)
        // o EnJustificacion (esperando que el trabajador presente la suya).
        $abandonos = Papeleta::where('causa_finalizacion_sin_retorno', 'abandono_no_marcado')
            ->when(array_key_exists($this->causa, self::CAUSAS), fn ($q) => $q->where('causa_finalizacion_sin_retorno', $this->causa))
            ->when($this->buscar, fn ($q) => $q->whereHas('trabajador', fn ($t) => $t
                ->where('name', 'like', "%{$this->buscar}%")
                ->orWhere('apellido', 'like', "%{$this->buscar}%")))
            ->with([
                'trabajador',
                'motivo',
                'historial' => fn ($q) => $q->whereIn('estado_nuevo', ['Cerrada', 'Finalizada', 'EnJustificacion'])->with('actor'),
            ])
            ->latest('dia_operativo')
            ->orderByDesc('papeletas.id')
            ->paginate(15);

        return view('livewire.papeletas.rrhh-abandonos', ['abandonos' => $abandonos, 'causas' => self::CAUSAS]);
    }
}
