<?php

namespace App\Livewire\Catalogos;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Punto de entrada único a los catálogos de administración, en
 * reemplazo del panel de Filament (/admin), que ya fue retirado del
 * proyecto.
 *
 * Los 8 catálogos ya viven en Blade + Livewire puro, uno por uno,
 * cada uno con su propio par Index/Form (ver App\Livewire\Sedes,
 * Motivos, Turnos, Feriados, UnidadesOrganicas, Configuraciones,
 * HorarioRrhh y Usuarios).
 */
#[Layout('layouts.app')]
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
                ['nombre' => 'Horario de RRHH', 'listo' => true, 'ruta' => route('horario-rrhh.index')],
                ['nombre' => 'Usuarios', 'listo' => true, 'ruta' => route('usuarios-admin.index')],
            ],
        ]);
    }
}
