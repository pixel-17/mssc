<?php

namespace App\Livewire\Catalogos;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Punto de entrada único a los catálogos de administración.
 *
 * Los catálogos ya viven en Blade + Livewire puro, uno por uno,
 * cada uno con su propio par Index/Form (ver App\Livewire\Sedes,
 * Motivos, Turnos, Configuraciones y Usuarios). El horario de RRHH
 * ya no es un catálogo: se deriva del horario ordinario de
 * Configuraciones (ver RrhhHorarioService). Unidades orgánicas
 * tampoco es un catálogo aparte: su CRUD vive dentro de Organigrama
 * (ver App\Livewire\Organigrama\UnidadModal).
 *
 * Es solo un acceso rápido con ícono + descripción de una línea, mismo
 * criterio que el sidebar (ver NavegacionComposer).
 */
#[Layout('layouts.app')]
#[Title('Catálogos')]
class CatalogoIndex extends Component
{
    public function render(): View
    {
        return view('livewire.catalogos.catalogo-index', [
            'catalogos' => [
                ['nombre' => 'Sedes', 'descripcion' => 'Locales con geocerca para marcar el retorno.', 'icono' => 'map-pin', 'ruta' => route('sedes.index')],
                ['nombre' => 'Motivos', 'descripcion' => 'Motivos de papeleta y sus reglas de sustento.', 'icono' => 'tag', 'ruta' => route('motivos.index')],
                ['nombre' => 'Turnos', 'descripcion' => 'Hora de inicio y fin de cada turno (Mañana, Tarde, Noche y Día).', 'icono' => 'clock', 'ruta' => route('turnos.index')],
                ['nombre' => 'Usuarios', 'descripcion' => 'Cuentas, roles y activación/desactivación.', 'icono' => 'user-circle', 'ruta' => route('usuarios-admin.index')],
                ['nombre' => 'Configuraciones', 'descripcion' => 'Parámetros globales del sistema.', 'icono' => 'cog', 'ruta' => route('configuraciones.index')],
            ],
        ]);
    }
}