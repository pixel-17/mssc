<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use App\Support\PatronDescansoSemanal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
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
#[Title('Configurar turno')]
class ConfiguracionTurnoForm extends Component
{
    #[Locked]
    public User $trabajador;

    public string $turno = '';

    public string $fechaAncla = '';

    public int $diasTrabajo = 6;

    public int $diasDescanso = 1;

    /**
     * Selector alternativo: en vez de calcular a mano en qué posición
     * del ciclo cae cada día, el admin/jefe marca directamente qué
     * días de la semana son de descanso (1=lunes .. 7=domingo). Solo
     * tiene sentido para un ciclo semanal (7 días), que es el caso
     * normal de 276 y de muchos 728. aplicarDiasSemana() lo traduce a
     * diasTrabajo/diasDescanso/fechaAncla.
     *
     * @var array<int, int>
     */
    public array $diasSemanaDescanso = [];

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

    /**
     * Traduce la selección de días de la semana a diasTrabajo/diasDescanso
     * (siempre 7 entre los dos, una semana) y a una fechaAncla concreta:
     * el primer día de trabajo de la semana que contiene la fechaAncla
     * actual (o la semana de hoy si todavía no hay ninguna). Usa la
     * misma cuenta que el generador (esDescansoEnFecha) para que la
     * vista previa de abajo sea exactamente lo que se va a guardar.
     */
    public function aplicarDiasSemana(): void
    {
        $referencia = $this->fechaAncla !== '' ? Carbon::parse($this->fechaAncla) : now();
        $resultado = PatronDescansoSemanal::anclaYCiclo($this->diasSemanaDescanso, $referencia);

        if ($resultado === null) {
            throw ValidationException::withMessages([
                'diasSemanaDescanso' => count($this->diasSemanaDescanso) >= 7 || $this->diasSemanaDescanso === []
                    ? 'Elige entre 1 y 6 días de descanso.'
                    : 'Los días de descanso deben ser consecutivos dentro de la semana (ej. sábado y domingo, o lunes y martes). Para descansos salteados, carga días de trabajo/descanso por número más abajo.',
            ]);
        }

        $this->diasTrabajo = $resultado['dias_trabajo'];
        $this->diasDescanso = $resultado['dias_descanso'];
        $this->fechaAncla = $resultado['fecha_ancla']->toDateString();
    }

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

    /**
     * 14 días desde el lunes de la semana actual, marcados
     * trabajo/descanso con la MISMA cuenta que se va a guardar
     * (GeneradorTurnoMensualService::esDescansoEnFecha). Así el admin
     * ve el resultado real antes de guardar, sin reimplementar la
     * lógica del ciclo en Blade/JS.
     *
     * @return array<int, array{fecha: string, etiqueta: string, descanso: bool}>|null
     */
    private function previsualizacion(): ?array
    {
        if ($this->fechaAncla === '' || $this->diasTrabajo < 1 || $this->diasDescanso < 1) {
            return null;
        }

        try {
            $ancla = Carbon::parse($this->fechaAncla);
        } catch (\Throwable) {
            return null;
        }

        $inicio = now()->startOfWeek(Carbon::MONDAY);

        return collect(range(0, 13))
            ->map(function (int $i) use ($inicio, $ancla) {
                $dia = $inicio->copy()->addDays($i);

                return [
                    'fecha' => $dia->toDateString(),
                    'etiqueta' => ucfirst($dia->translatedFormat('D d')),
                    'descanso' => GeneradorTurnoMensualService::esDescansoEnFecha($ancla, $dia, $this->diasTrabajo, $this->diasDescanso),
                ];
            })
            ->all();
    }

    public function render(): View
    {
        return view('livewire.turnos.configuracion-turno-form', [
            'opcionesTurno' => ConfiguracionTurno::turnosValidosPara($this->trabajador),
            'previsualizacion' => $this->previsualizacion(),
        ]);
    }
}
