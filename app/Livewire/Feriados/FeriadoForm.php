<?php

namespace App\Livewire\Feriados;

use App\Models\Feriado;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Reemplaza a FeriadoResource::form() de Filament. Este catálogo lo
 * usa CalculadorDiasHabiles para las 48h hábiles de sustento (Salud).
 */
#[Layout('layouts.app')]
#[Title('Feriado')]
class FeriadoForm extends Component
{
    use RequiereAdmin;

    #[Locked]
    public ?Feriado $feriado = null;

    public string $fecha = '';

    public ?string $descripcion = null;

    public function mount(?Feriado $feriado = null): void
    {
        if ($feriado?->exists) {
            $this->feriado = $feriado;
            $this->fecha = optional($feriado->fecha)->toDateString() ?? '';
            $this->descripcion = $feriado->descripcion;
        }
    }

    protected function rules(): array
    {
        return [
            'fecha' => [
                'required',
                'date',
                Rule::unique('feriados', 'fecha')->ignore($this->feriado?->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function guardar(): void
    {
        $this->autorizarAdmin();

        $datos = $this->validate();

        $this->feriado
            ? $this->feriado->update($datos)
            : Feriado::create($datos);

        session()->flash('mensaje', $this->feriado ? 'Feriado actualizado.' : 'Feriado creado.');

        $this->redirectRoute('feriados.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.feriados.feriado-form');
    }
}
