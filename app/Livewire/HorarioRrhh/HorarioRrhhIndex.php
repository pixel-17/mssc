<?php

namespace App\Livewire\HorarioRrhh;

use App\Models\HorarioRrhh;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Listado del Horario de RRHH en Blade + Livewire puro (reemplaza a
 * HorarioRrhhResource::table() de Filament). Filas fijas por día de
 * semana sembradas por HorarioRrhhSeeder — no hay creación ni borrado
 * aquí, solo edición (ver HorarioRrhhForm).
 */
#[Layout('layouts.app')]
class HorarioRrhhIndex extends Component
{
    public const DIAS = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    public function render(): View
    {
        return view('livewire.horario-rrhh.horario-rrhh-index', [
            'horarios' => HorarioRrhh::orderBy('dia_semana')->get(),
        ]);
    }
}
