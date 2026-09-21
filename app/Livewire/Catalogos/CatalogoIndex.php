<?php

namespace App\Livewire\Catalogos;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Punto de entrada único a los catálogos de administración, en
 * reemplazo del panel de Filament (/admin), que ya fue retirado del
 * proyecto.
 *
 * Los 7 catálogos ya viven en Blade + Livewire puro, uno por uno,
 * cada uno con su propio par Index/Form (ver App\Livewire\Sedes,
 * Motivos, Turnos, Feriados, UnidadesOrganicas, Configuraciones y
 * Usuarios). El horario de RRHH ya no es un catálogo: se deriva del
 * horario ordinario de Configuraciones (ver RrhhHorarioService).
 */
#[Layout('layouts.app')]
#[Title('Catálogos')]
class CatalogoIndex extends Component
{
    public function render(): View
    {
        return view('livewire.catalogos.catalogo-index', [
            'catalogos' => [
                ['nombre' => 'Sedes', 'listo' => true, 'ruta' => route('sedes.index')],
                ['nombre' => 'Motivos', 'listo' => true, 'ruta' => route('motivos.index')],
                ['nombre' => 'Turnos', 'listo' => true, 'ruta' => route('turnos.index')],
                ['nombre' => 'Feriados', 'listo' => true, 'ruta' => route('feriados.index')],
                ['nombre' => 'Unidades orgánicas', 'listo' => true, 'ruta' => route('unidades-organicas.index')],
                ['nombre' => 'Configuraciones', 'listo' => true, 'ruta' => route('configuraciones.index')],
                ['nombre' => 'Usuarios', 'listo' => true, 'ruta' => route('usuarios-admin.index')],
            ],
        ]);
    }
}
