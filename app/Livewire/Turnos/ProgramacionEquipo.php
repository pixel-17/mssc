<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\CalculadorDiasHabiles;
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
 * Matriz de programación de equipo (régimen 728): filas = trabajadores
 * 728 activos que este jefe supervisa (mismo alcance que
 * CalendarioEquipoIndex, ver EquipoDelJefeService), columnas = días del
 * mes. Se pinta con el mismo "pincel" que ProgramacionMensual, con
 * extras de equipo: selección de filas, patrón escalonado y conteo de
 * cobertura por día.
 *
 * El pintado ocurre en el navegador (Alpine). Al guardar solo viajan
 * los trabajadores que cambiaron, y el servidor vuelve a resolver el
 * equipo: un id ajeno al alcance del jefe se rechaza.
 */
#[Layout('layouts.app')]
#[Title('Programar turnos — mi equipo (728)')]
class ProgramacionEquipo extends Component
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
        // Misma regla que ConfiguracionTurnoForm/ProgramacionMensual vía
        // puedeGestionarTurnoDe(): auto-gestionarse el turno solo lo puede
        // hacer quien es jefe de alguien. Sin esto, EquipoDelJefeService::
        // para() igual mete al propio actor en el equipo (push($user)), y
        // cualquier trabajador 728 -- sin ser jefe de nadie -- podía
        // editar su propio horario desde esta pantalla de equipo.
        $actor = auth()->user();
        abort_unless($actor?->hasRole('admin') || (bool) $actor?->esJefeDeAlguien(), 403);

        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    public function mesAnterior(): void
    {
        $this->irA($this->primerDiaDelMes()->subMonthNoOverflow());
    }

    public function mesSiguiente(): void
    {
        $this->irA($this->primerDiaDelMes()->addMonthNoOverflow());
    }

    private function primerDiaDelMes(): Carbon
    {
        return Carbon::create($this->anio, $this->mes, 1) ?? now();
    }

    public function irAHoy(): void
    {
        $this->irA(now());
    }

    /**
     * Primer paso: valida, y si hay advertencias las muestra y espera
     * confirmación (guardarIgualmente); si no, guarda directo.
     *
     * @param  array<int|string, array<string, string>>  $cambios  userId => dias (solo filas modificadas)
     */
    public function guardar(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->mensaje = null;

        if ($cambios === []) {
            $this->advertencias = [];
            $this->mensaje = 'No hay cambios que guardar.';

            return;
        }

        $equipo = $this->equipo();

        // El payload viene del navegador: se valida antes de calcular nada.
        $servicio->validarEquipo($equipo, $this->anio, $this->mes, $cambios);

        $this->advertencias = $servicio->advertenciasEquipo($equipo, $cambios);

        if ($this->advertencias === []) {
            $this->persistir($servicio, $equipo, $cambios);
        }
    }

    /**
     * @param  array<int|string, array<string, string>>  $cambios
     */
    public function guardarIgualmente(ProgramacionTurnoService $servicio, array $cambios): void
    {
        $this->persistir($servicio, $this->equipo(), $cambios);
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
        /** @var User $actor */
        $actor = auth()->user();

        $servicio->guardarEquipo($equipo, $this->anio, $this->mes, $cambios, $actor);

        $this->advertencias = [];
        $this->mensaje = 'Programación de '.$this->primerDiaDelMes()->translatedFormat('F Y')
            .' guardada para '.count($cambios).' '.(count($cambios) === 1 ? 'trabajador' : 'trabajadores').'.';
        $this->version++;
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
     * Trabajadores 728 activos dentro del alcance del jefe, con el id
     * como clave. Se resuelve de nuevo en cada acción: nunca se confía
     * en la lista que vio el navegador.
     *
     * @return Collection<int, User>
     */
    private function equipo(): Collection
    {
        /** @var User $actor */
        $actor = auth()->user();

        [$trabajadores, , $soloLectura] = app(EquipoDelJefeService::class)->para($actor);

        return $trabajadores
            ->filter(fn (User $t) => $t->regimen === '728' && $t->activo && ! in_array($t->id, $soloLectura, true))
            ->keyBy('id');
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        [$todos, $esJefeDeArea, $soloLectura] = app(EquipoDelJefeService::class)->para($actor);

        // Los jefes pares que solo se ven (solo lectura) no se programan aquí.
        $todos = $todos->reject(fn (User $t) => in_array($t->id, $soloLectura, true))->values();

        $equipo = $todos->filter(fn (User $t) => $t->regimen === '728' && $t->activo)->values();

        $inicioMes = $this->primerDiaDelMes()->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();
        $validos = [...ConfiguracionTurno::TURNOS_728, ProgramacionTurnoService::DESCANSO];

        $turnosPorUsuario = Turno::whereIn('user_id', $equipo->pluck('id'))
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->groupBy('user_id');

        // Estado inicial: { "userId": { "Y-m-d": código } }. Objetos (no
        // listas) para que un mes o un trabajador vacío serialicen como {}.
        $diasIniciales = [];
        foreach ($equipo as $trabajador) {
            $mapa = ($turnosPorUsuario->get($trabajador->id) ?? collect())
                ->mapWithKeys(fn (Turno $t) => [$t->fecha->toDateString() => $t->codigo()])
                ->filter(fn (string $codigo) => in_array($codigo, $validos, true))
                ->all();

            $diasIniciales[(string) $trabajador->id] = (object) $mapa;
        }

        $fechas = collect(range(1, $finMes->day))->map(fn (int $d) => $inicioMes->copy()->day($d));

        $diasHabiles = app(CalculadorDiasHabiles::class);
        $noLaborables = $fechas
            ->filter(fn (Carbon $f) => ! $diasHabiles->esDiaLaborable($f))
            ->map(fn (Carbon $f) => $f->toDateString())
            ->values()
            ->all();

        $generador = app(GeneradorTurnoMensualService::class);
        $horas = [];
        foreach (ConfiguracionTurno::TURNOS_728 as $codigo) {
            [$inicio, $fin] = $generador->horasDe($codigo);
            $horas[$codigo] = substr($inicio, 0, 5).' – '.substr($fin, 0, 5);
        }

        return view('livewire.turnos.programacion-equipo', [
            'equipo' => $equipo,
            'esJefeDeArea' => $esJefeDeArea,
            'sinProgramacionDiaria' => $todos->count() - $equipo->count(),
            'inicioMes' => $inicioMes,
            'fechas' => $fechas,
            'fechasIso' => $fechas->map(fn (Carbon $f) => $f->toDateString())->values()->all(),
            'uids' => $equipo->map(fn (User $t) => (string) $t->id)->values()->all(),
            'diasIniciales' => (object) $diasIniciales,
            'horas' => $horas,
            'hoy' => now()->toDateString(),
            'noLaborables' => $noLaborables,
        ]);
    }
}
