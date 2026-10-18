<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  string|null  $title  Título explícito de la pestaña (prioridad 1 en
     *                              TituloDePagina::resolver), para páginas Blade
     *                              estáticas que no quieren (o no pueden) usar el
     *                              slot `header` visible, p. ej. profile/show.blade.php.
     */
    public function __construct(public ?string $title = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
