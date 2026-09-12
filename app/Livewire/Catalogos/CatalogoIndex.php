<?php

namespace App\Livewire\Catalogos;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Punto de entrada único a los catálogos de administración, en
 * reemplazo del panel de Filament (/admin) que se va a eliminar.
 *
 * Mientras dure la migración, cada catálogo listado abajo apunta a
 * SU PROPIA versión más nueva: Sedes ya vive en Blade/Livewire
 * (route('sedes.index')); el resto SIGUE en Filament (/admin/...)
 * hasta que se migre uno por uno — por eso Filament no se puede
 * borrar todavía sin dejar esos 6 catálogos sin pantalla.
 *
 * Actualiza el array $catalogos de este componente a medida que cada
 * catálogo se vaya migrando (cambia 'listo' a true y la 'ruta' a la
 * nueva route() de Livewire).
 */
#[Layout('layouts.app')]
class CatalogoIndex extends Component
{
    public function render(): View
    {
        return view('livewire.catalogos.catalogo-index', [
            'catalogos' => [
                ['nombre' => 'Sedes', 'listo' => true, 'ruta' => route('sedes.index')],
                ['nombre' => 'Motivos', 'listo' => false, 'ruta' => '/admin/motivos'],
                ['nombre' => 'Turnos', 'listo' => false, 'ruta' => '/admin/turnos'],
                ['nombre' => 'Feriados', 'listo' => false, 'ruta' => '/admin/feriados'],
                ['nombre' => 'Unidades orgánicas', 'listo' => false, 'ruta' => '/admin/unidad-organicas'],
                ['nombre' => 'Configuraciones', 'listo' => false, 'ruta' => '/admin/configuracions'],
                ['nombre' => 'Horario de RRHH', 'listo' => false, 'ruta' => '/admin/horario-rrhhs'],
                ['nombre' => 'Usuarios', 'listo' => false, 'ruta' => '/admin/users'],
            ],
        ]);
    }
}
