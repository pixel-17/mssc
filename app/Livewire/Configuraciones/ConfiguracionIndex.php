<?php

namespace App\Livewire\Configuraciones;

use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Listado del catálogo de Configuraciones en Blade + Livewire puro
 * Filas
 * fijas sembradas por ConfiguracionSeeder — no hay creación ni borrado
 * aquí, solo edición del valor (ver ConfiguracionForm).
 */
#[Layout('layouts.app')]
#[Title('Configuraciones')]
class ConfiguracionIndex extends Component
{
    public function render(): View
    {
        return view('livewire.configuraciones.configuracion-index', [
            // Las horas de los turnos se editan solo en Turnos (DefinicionTurnos).
            'configuraciones' => Configuracion::orderBy('clave')->get()
                ->reject(fn (Configuracion $c) => ConfiguracionForm::esClaveDeTurno($c->clave))
                ->values(),
        ]);
    }
}
