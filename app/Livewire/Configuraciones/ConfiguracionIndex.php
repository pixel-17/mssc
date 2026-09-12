<?php

namespace App\Livewire\Configuraciones;

use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Listado del catálogo de Configuraciones en Blade + Livewire puro
 * (reemplaza a ConfiguracionResource::table() de Filament). Filas
 * fijas sembradas por ConfiguracionSeeder — no hay creación ni borrado
 * aquí, solo edición del valor (ver ConfiguracionForm).
 */
#[Layout('layouts.app')]
class ConfiguracionIndex extends Component
{
    public function render(): View
    {
        return view('livewire.configuraciones.configuracion-index', [
            'configuraciones' => Configuracion::orderBy('clave')->get(),
        ]);
    }
}
