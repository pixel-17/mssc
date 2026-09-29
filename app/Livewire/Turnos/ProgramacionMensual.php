<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use App\Services\ProgramacionTurnoService;
use App\Support\PatronTurnos;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Programación día por día (régimen 728): calendario mensual donde se
 * elige un "pincel" (Mañana/Tarde/Noche/Descanso) y se pinta con
 * clics o arrastrando. Todo el pintado ocurre en el navegador
 * (Alpine); el servidor solo recibe el mes completo al guardar.
 *
 * Misma autorización que ConfiguracionTurnoForm
 * (User::puedeGestionarTurnoDe). Ver ProgramacionTurnoService para la
 * regla de escritura y las advertencias.
 */
#[Layout('layouts.app')]
#[Title('Programar turnos')]
class ProgramacionMensual extends Component
{
    #[Locked]
    public User $trabajador;

    public int $anio;

    public int $mes;

    /** Se incrementa al cambiar de mes o guardar para reiniciar el estado Alpine de la grilla. */
    #[Locked]
    public int $version = 0;

    /** @var array<int, string> */
    #[Locked]
    public array $advertencias = [];

    #[Locked]
    public ?string $mensaje = null;

    /**
     * Meses generados con un patrón que esperan confirmación por tener
     * advertencias ('Y-m' => dias). Locked: el navegador no puede
     * cambiarlo, solo se llena desde generarMeses().
     *
     * @var array<string, array<string, string>>
     */
    #[Locked]
    public array $planPendiente = [];

    public function mount(User $trabajador): void
    {
        $this->autorizar($trabajador);

        $this->trabajador = $trabajador;
        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    public function mesAnterior(): void
    {
        $this->irA(Carbon::create($this->anio, $this->mes, 1)->subMonthNoOverflow());
    }

    public function mesSiguiente(): void
    {
        $this->irA(Carbon::create($this->anio, $this->mes, 1)->addMonthNoOverflow());
    }

    public function irAHoy(): void
    {
        $this->irA(now());
    }

    /**
     * Primer paso: si hay advertencias las muestra y espera
     * confirmación (guardarIgualmente); si no, guarda directo.
     *
     * @param  array<string, string>  $dias
     */
    public function guardar(ProgramacionTurnoService $servicio, array $dias): void
    {
        $this->autorizar($this->trabajador);

        // Valida antes de calcular advertencias: el payload viene del navegador.
        $servicio->validar($this->trabajador, $this->anio, $this->mes, $dias);

        $this->advertencias = $servicio->advertencias($dias);
        $this->mensaje = null;

        if ($this->advertencias === []) {
            $this->persistir($servicio, $dias);
        }
    }

    /**
     * @param  array<string, string>  $dias
     */
    public function guardarIgualmente(ProgramacionTurnoService $servicio, array $dias): void
    {
        $this->autorizar($this->trabajador);

        $this->persistir($servicio, $dias);
    }

    /**
     * Repite un patrón durante varios meses (a partir de $desde, dentro
     * del mes que se está viendo) y lo guarda. $dias es la grilla actual:
     * lo pintado antes de $desde en este mes se conserva.
     *
     * Con advertencias no guarda: las muestra y espera confirmación
     * (guardarPlanIgualmente), igual que guardar().
     *
     * @param  array<string, string>  $dias
     */
    public function generarMeses(ProgramacionTurnoService $servicio, string $patron, string $desde, int $meses, bool $continuar, array $dias): void
    {
        $this->autorizar($this->trabajador);

        $pasos = PatronTurnos::parsear($patron);
        if ($pasos === null) {
            throw ValidationException::withMessages(['dias' => 'Usa solo M, T, N o D separados por espacios. Ej.: M M T T N D']);
        }

        $desdeOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) === 1
            && str_starts_with($desde, sprintf('%04d-%02d-', $this->anio, $this->mes))
            && checkdate($this->mes, (int) substr($desde, 8, 2), $this->anio);

        if (! $desdeOk) {
            throw ValidationException::withMessages(['dias' => 'Elige un día "desde" dentro del mes que estás viendo.']);
        }

        // El payload viene del navegador: se valida antes de usarlo.
        $servicio->validar($this->trabajador, $this->anio, $this->mes, $dias);

        $plan = $servicio->planPatron($this->trabajador, $pasos, $desde, $meses, $continuar, $dias);

        $this->advertencias = $servicio->advertenciasPlan($plan);
        $this->mensaje = null;

        if ($this->advertencias === []) {
            $this->persistirPlan($servicio, $plan);
        } else {
            $this->planPendiente = $plan;
        }
    }

    public function guardarPlanIgualmente(ProgramacionTurnoService $servicio): void
    {
        $this->autorizar($this->trabajador);

        if ($this->planPendiente !== []) {
            $this->persistirPlan($servicio, $this->planPendiente);
        }
    }

    public function descartarAdvertencias(): void
    {
        $this->advertencias = [];
        $this->planPendiente = [];
    }

    /**
     * @param  array<string, string>  $dias
     */
    private function persistir(ProgramacionTurnoService $servicio, array $dias): void
    {
        $servicio->guardarMes($this->trabajador, $this->anio, $this->mes, $dias, auth()->user());

        $this->advertencias = [];
        $this->mensaje = 'Programación de '.Carbon::create($this->anio, $this->mes, 1)->translatedFormat('F Y').' guardada.';
        $this->version++;
    }

    /**
     * @param  array<string, array<string, string>>  $plan
     */
    private function persistirPlan(ProgramacionTurnoService $servicio, array $plan): void
    {
        $servicio->guardarPlan($this->trabajador, $plan, auth()->user());

        $claves = array_keys($plan);
        $primero = Carbon::createFromFormat('Y-m-d', $claves[0].'-01');
        $ultimo = Carbon::createFromFormat('Y-m-d', end($claves).'-01');

        $this->advertencias = [];
        $this->planPendiente = [];
        $this->mensaje = count($plan) === 1
            ? 'Programación de '.$primero->translatedFormat('F Y').' guardada.'
            : 'Programación guardada de '.$primero->translatedFormat('F Y').' a '.$ultimo->translatedFormat('F Y').'.';
        $this->version++;
    }

    private function irA(Carbon $fecha): void
    {
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
        $this->advertencias = [];
        $this->planPendiente = [];
        $this->mensaje = null;
        $this->version++;
    }

    private function autorizar(User $trabajador): void
    {
        abort_unless(auth()->user()->puedeGestionarTurnoDe($trabajador), 403);
        abort_unless($trabajador->regimen === '728', 403, 'La programación día por día solo aplica al régimen 728.');
    }

    public function render(): View
    {
        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $validos = [...ConfiguracionTurno::TURNOS_728, ProgramacionTurnoService::DESCANSO];

        // Estado inicial de la grilla: ['Y-m-d' => código]. Un objeto (no
        // lista) para que un mes vacío se serialice como {} y no como [].
        $dias = Turno::where('user_id', $this->trabajador->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->mapWithKeys(fn (Turno $t) => [$t->fecha->toDateString() => $t->codigo()])
            ->filter(fn (string $codigo) => in_array($codigo, $validos, true))
            ->all();

        // Grilla de semanas (lunes a domingo), null = celda vacía.
        $celdas = array_fill(0, $inicioMes->dayOfWeekIso - 1, null);
        for ($dia = 1; $dia <= $finMes->day; $dia++) {
            $celdas[] = $inicioMes->copy()->day($dia)->toDateString();
        }
        while (count($celdas) % 7 !== 0) {
            $celdas[] = null;
        }

        // Últimos 14 días del mes anterior, para "continuar la rotación" en el navegador.
        $previosMes = Turno::where('user_id', $this->trabajador->id)
            ->whereBetween('fecha', [
                $inicioMes->copy()->subDays(14)->toDateString(),
                $inicioMes->copy()->subDay()->toDateString(),
            ])
            ->get()
            ->mapWithKeys(fn (Turno $t) => [$t->fecha->toDateString() => $t->codigo()])
            ->filter(fn (string $codigo) => in_array($codigo, $validos, true))
            ->all();

        $generador = app(GeneradorTurnoMensualService::class);
        $horas = [];
        foreach (ConfiguracionTurno::TURNOS_728 as $codigo) {
            [$inicio, $fin] = $generador->horasDe($codigo);
            $horas[$codigo] = substr($inicio, 0, 5).' – '.substr($fin, 0, 5);
        }

        return view('livewire.turnos.programacion-mensual', [
            'horas' => $horas,
            'fechasMes' => array_values(array_filter($celdas)),
            'inicioMes' => $inicioMes,
            'semanas' => array_chunk($celdas, 7),
            'diasIniciales' => (object) $dias,
            'previosMes' => (object) $previosMes,
            'predefinidos' => PatronTurnos::PREDEFINIDOS,
            'maxMeses' => PatronTurnos::MAX_MESES,
            'hoy' => now()->toDateString(),
        ]);
    }
}
