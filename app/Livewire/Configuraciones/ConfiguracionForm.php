<?php

namespace App\Livewire\Configuraciones;

use App\Models\Configuracion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reemplaza a ConfiguracionResource::form() de Filament. Admin solo
 * puede EDITAR el valor de cada clave (reloj del jefe, tope de
 * observaciones, bloque de almuerzo, horas de sustento, días de
 * subsanación), nunca crear ni borrar filas — por eso este componente
 * exige un registro existente (no admite modo "crear").
 */
#[Layout('layouts.app')]
class ConfiguracionForm extends Component
{
    public Configuracion $configuracion;

    public string $valor = '';

    public function mount(Configuracion $configuracion): void
    {
        $this->configuracion = $configuracion;
        $this->valor = $configuracion->valor;
    }

    protected function rules(): array
    {
        return [
            'valor' => ['required', 'string', 'max:255'],
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        $this->configuracion->update($datos);

        session()->flash('mensaje', 'Configuración actualizada.');

        $this->redirectRoute('configuraciones.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.configuraciones.configuracion-form');
    }
}
