<?php

namespace App\Livewire\HorarioRrhh;

use App\Models\HorarioRrhh;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reemplaza a HorarioRrhhResource::form() de Filament. Se consulta en
 * cada aprobación de jefe (RrhhHorarioService) — admin solo edita
 * hora_inicio/hora_fin/activo, nunca crea ni borra días, por eso este
 * componente exige un registro existente.
 */
#[Layout('layouts.app')]
class HorarioRrhhForm extends Component
{
    public HorarioRrhh $horario;

    public string $horaInicio = '';

    public string $horaFin = '';

    public bool $activo = true;

    public function mount(HorarioRrhh $horario): void
    {
        $this->horario = $horario;
        $this->horaInicio = $horario->hora_inicio;
        $this->horaFin = $horario->hora_fin;
        $this->activo = $horario->activo;
    }

    protected function rules(): array
    {
        return [
            'horaInicio' => ['required', 'date_format:H:i'],
            'horaFin' => ['required', 'date_format:H:i', 'after:horaInicio'],
            'activo' => ['boolean'],
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        $this->horario->update([
            'hora_inicio' => $datos['horaInicio'],
            'hora_fin' => $datos['horaFin'],
            'activo' => $datos['activo'],
        ]);

        session()->flash('mensaje', 'Horario de RRHH actualizado.');

        $this->redirectRoute('horario-rrhh.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.horario-rrhh.horario-rrhh-form', [
            'nombreDia' => HorarioRrhhIndex::DIAS[$this->horario->dia_semana] ?? $this->horario->dia_semana,
        ]);
    }
}
