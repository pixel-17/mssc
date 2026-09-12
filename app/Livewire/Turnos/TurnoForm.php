<?php

namespace App\Livewire\Turnos;

use App\Models\Sede;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reemplaza a TurnoResource::form() de Filament. Único índice
 * user_id+fecha (ver migración) — la validación de abajo replica esa
 * misma restricción.
 */
#[Layout('layouts.app')]
class TurnoForm extends Component
{
    public ?Turno $turno = null;

    public ?int $userId = null;

    public ?int $sedeId = null;

    public string $fecha = '';

    public bool $esDescanso = false;

    public ?string $horaInicio = null;

    public ?string $horaFin = null;

    public function mount(?Turno $turno = null): void
    {
        if ($turno?->exists) {
            $this->turno = $turno;
            $this->userId = $turno->user_id;
            $this->sedeId = $turno->sede_id;
            $this->fecha = optional($turno->fecha)->toDateString() ?? '';
            $this->esDescanso = $turno->es_descanso;
            $this->horaInicio = $turno->hora_inicio;
            $this->horaFin = $turno->hora_fin;
        }
    }

    protected function rules(): array
    {
        return [
            'userId' => [
                'required',
                'exists:users,id',
                Rule::unique('turnos', 'user_id')
                    ->where(fn ($query) => $query->where('fecha', $this->fecha))
                    ->ignore($this->turno?->id),
            ],
            'sedeId' => ['nullable', 'exists:sedes,id'],
            'fecha' => ['required', 'date'],
            'esDescanso' => ['boolean'],
            'horaInicio' => [$this->esDescanso ? 'nullable' : 'required', 'date_format:H:i'],
            'horaFin' => [$this->esDescanso ? 'nullable' : 'required', 'date_format:H:i'],
        ];
    }

    protected $messages = [
        'userId.unique' => 'Este trabajador ya tiene un turno registrado en esa fecha.',
    ];

    public function guardar(): void
    {
        $datos = $this->validate();

        $atributos = [
            'user_id' => $datos['userId'],
            'sede_id' => $datos['sedeId'],
            'fecha' => $datos['fecha'],
            'es_descanso' => $datos['esDescanso'],
            'hora_inicio' => $datos['esDescanso'] ? null : $datos['horaInicio'],
            'hora_fin' => $datos['esDescanso'] ? null : $datos['horaFin'],
        ];

        $this->turno
            ? $this->turno->update($atributos)
            : Turno::create($atributos);

        session()->flash('mensaje', $this->turno ? 'Turno actualizado.' : 'Turno creado.');

        $this->redirectRoute('turnos.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.turnos.turno-form', [
            'trabajadores' => User::orderBy('name')->pluck('name', 'id'),
            'sedes' => Sede::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }
}
