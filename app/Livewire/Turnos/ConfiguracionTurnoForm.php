<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pantalla para que Admin o Jefe (Inmediato/Área, ver
 * User::puedeGestionarTurnoDe) carguen o cambien el ciclo de turno de
 * un trabajador. Guardar aquí SIEMPRE marca el mes de $fechaAncla
 * como 'manual' (ver GeneradorTurnoMensualService::cargarConfiguracion)
 * — eso es lo que evita que el comando automático del mes siguiente
 * lo pise mientras sigan sin tocar esta pantalla.
 */
#[Layout('layouts.app')]
class ConfiguracionTurnoForm extends Component
{
    #[Locked]
    public User $trabajador;

    public string $turno = '';

    public string $fechaAncla = '';

    public int $diasTrabajo = 6;

    public int $diasDescanso = 1;

    public function mount(User $trabajador): void
    {
        abort_unless(auth()->user()->puedeGestionarTurnoDe($trabajador), 403);

        $this->trabajador = $trabajador;

        $config = ConfiguracionTurno::where('user_id', $trabajador->id)->first();

        if ($config) {
            $this->turno = $config->turno;
            $this->fechaAncla = $config->fecha_ancla->toDateString();
            $this->diasTrabajo = $config->dias_trabajo;
            $this->diasDescanso = $config->dias_descanso;
        } else {
            // 276 solo tiene un turno válido (Día): se preselecciona
            // para no obligar a elegir algo que no tiene opciones.
            $opciones = ConfiguracionTurno::turnosValidosPara($trabajador);
            $this->turno = count($opciones) === 1 ? $opciones[0] : '';
            $this->fechaAncla = now()->toDateString();
        }
    }

    protected function rules(): array
    {
        return [
            'turno' => ['required', 'in:'.implode(',', ConfiguracionTurno::turnosValidosPara($this->trabajador))],
            'fechaAncla' => ['required', 'date'],
            'diasTrabajo' => ['required', 'integer', 'min:1', 'max:30'],
            'diasDescanso' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    protected $messages = [
        'turno.in' => 'Ese turno no existe para el régimen de este trabajador.',
    ];

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        abort_unless(auth()->user()->puedeGestionarTurnoDe($this->trabajador), 403);

        $datos = $this->validate();

        $generador->cargarConfiguracion(
            trabajador: $this->trabajador,
            turno: $datos['turno'],
            fechaAncla: Carbon::parse($datos['fechaAncla']),
            actor: auth()->user(),
            diasTrabajo: $datos['diasTrabajo'],
            diasDescanso: $datos['diasDescanso'],
        );

        session()->flash('mensaje', 'Configuración de turno cargada. Se generó el horario del mes en curso.');

        $this->redirectRoute('turnos.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.turnos.configuracion-turno-form', [
            'opcionesTurno' => ConfiguracionTurno::turnosValidosPara($this->trabajador),
        ]);
    }
}
