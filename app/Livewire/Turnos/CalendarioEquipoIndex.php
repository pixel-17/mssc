<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\EquipoDelJefeService;
use App\Services\GeneradorTurnoMensualService;
use App\Services\ProgramacionTurnoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Vista de equipo (grilla): filas = trabajadores que este Jefe
 * (Inmediato o de Área) supervisa, columnas = días del mes.
 *
 * Régimen 728: la celda se pinta directo aquí (M/T/N/D) — se reutiliza
 * tal cual la lógica de pincel/patrón/guardado que antes solo existía
 * en "Programar equipo" (ver ProgramacionEquipo/ProgramacionTurnoService),
 * para no mantener dos motores de programación distintos.
 *
 * Régimen 276 (horario ordinario, ciclo fijo): la celda sigue siendo
 * de solo lectura — no tiene sentido "pintar" M/T/N/D día por día en
 * un ciclo de trabajo/descanso fijo — y el jefe sigue yendo a
 * turnos.configuracion para cargarlo o cambiarlo.
 *
 * Admin y Trabajador NO usan esta pantalla — ellos ven la vista
 * individual (CalendarioIndividualIndex).
 */
#[Layout('layouts.app')]
#[Title('Calendario de turnos — mi equipo')]
class CalendarioEquipoIndex extends Component
{
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

    public function mount(): void
    {
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

    private function irA(Carbon $fecha): void
    {
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
        $this->advertencias = [];
        $this->mensaje = null;
        $this->version++;
    }

    /**
     * Primer paso: valida, y si hay advertencias las muestra y espera
     * confirmación (guardarIgualmente); si no, guarda directo.
     *
     * @param  array<int|string, array<string, string>>  $cambios  userId => dias (solo filas 728 modificadas)
     */
    public function guardar(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->mensaje = null;

        if ($cambios === []) {
            $this->advertencias = [];
            $this->mensaje = 'No hay cambios que guardar.';

            return;
        }

        $equipo728 = $this->equipo728();

        $servicio->validarEquipo($equipo728, $this->anio, $this->mes, $cambios);

        $this->advertencias = $servicio->advertenciasEquipo($equipo728, $cambios);

        if ($this->advertencias === []) {
            $this->persistir($servicio, $equipo728, $cambios);
        }
    }

    /**
     * @param  array<int|string, array<string, string>>  $cambios
     */
    public function guardarIgualmente(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->persistir($servicio, $this->equipo728(), $cambios);
    }

    public function descartarAdvertencias(): void
    {
        $this->advertencias = [];
    }

    /**
     * @param  Collection<int, User>  $equipo
     * @param  array<int|string, array<string, string>>  $cambios
     */
    private function persistir(ProgramacionTurnoService $servicio, Collection $equipo, array $cambios): void
    {
        $servicio->guardarEquipo($equipo, $this->anio, $this->mes, $cambios, auth()->user());

        $this->advertencias = [];
        $this->mensaje = 'Turnos guardados para '.count($cambios).' '.(count($cambios) === 1 ? 'trabajador' : 'trabajadores').'.';
        $this->version++;
    }

    /**
     * Subconjunto 728 activo del equipo, con el id como clave (lo que
     * esperan ProgramacionTurnoService::validarEquipo/guardarEquipo).
     * Se resuelve de nuevo en cada acción: nunca se confía en la lista
     * que vio el navegador.
     *
     * @return Collection<int, User>
     */
    private function equipo728(): Collection
    {
        [$trabajadores] = app(EquipoDelJefeService::class)->para(auth()->user());

        return $trabajadores
            ->filter(fn (User $t) => $t->regimen === '728' && $t->activo)
            ->keyBy('id');
    }

    public function render(): View
    {
        [$trabajadores, $esJefeDeArea] = app(EquipoDelJefeService::class)->para(auth()->user());

        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();
        $dias = collect(range(1, $finMes->day));
        $fechas = collect(range(1, $finMes->day))->map(fn (int $d) => $inicioMes->copy()->day($d));

        $turnosPorUsuario = Turno::whereIn('user_id', $trabajadores->pluck('id'))
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->with('sede')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($turnos) => $turnos->keyBy(fn (Turno $turno) => $turno->fecha->day));

        // Estado inicial de la grilla pintable (solo régimen 728 activo):
        // { "userId": { "Y-m-d": código } }. Objeto (no lista) para que un
        // mes o trabajador vacío serialice como {}, no como [].
        $equipo728 = $this->equipo728();
        $validos728 = [...ConfiguracionTurno::TURNOS_728, ProgramacionTurnoService::DESCANSO];

        $diasIniciales = [];
        foreach ($equipo728 as $trabajador) {
            $mapa = ($turnosPorUsuario->get($trabajador->id) ?? collect())
                ->mapWithKeys(fn (Turno $t) => [$t->fecha->toDateString() => $t->codigo()])
                ->filter(fn (string $codigo) => in_array($codigo, $validos728, true))
                ->all();

            $diasIniciales[(string) $trabajador->id] = (object) $mapa;
        }

        $generador = app(GeneradorTurnoMensualService::class);
        $horas = [];
        foreach (ConfiguracionTurno::TURNOS_728 as $codigo) {
            [$inicio, $fin] = $generador->horasDe($codigo);
            $horas[$codigo] = substr($inicio, 0, 5).' – '.substr($fin, 0, 5);
        }

        return view('livewire.turnos.calendario-equipo-index', [
            'trabajadores' => $trabajadores,
            'esJefeDeArea' => $esJefeDeArea,
            'dias' => $dias,
            'fechas' => $fechas,
            'inicioMes' => $inicioMes,
            'turnosPorUsuario' => $turnosPorUsuario,
            'hoy' => now(),
            'uids728' => $equipo728->map(fn (User $t) => (string) $t->id)->values()->all(),
            'fechasIso' => $fechas->map(fn (Carbon $f) => $f->toDateString())->values()->all(),
            'diasIniciales' => (object) $diasIniciales,
            'horas' => $horas,
        ]);
    }
}
