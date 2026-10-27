<?php

namespace App\Livewire\Turnos;

use App\Models\Configuracion;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\CalculadorDiasHabiles;
use App\Services\EquipoDelJefeService;
use App\Services\GeneradorTurnoMensualService;
use App\Services\ProgramacionTurnoService;
use App\Support\PatronDescansoSemanal;
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
 * Régimen 276 (horario ordinario): la celda es de solo lectura, sin
 * ningún control para que el Jefe elija días de descanso desde aquí
 * — el panel de arriba solo refleja, informativamente, los días
 * laborables que el Admin dejó en Configuraciones
 * (HORARIO_ORDINARIO_DIAS_LABORABLES). Crear o editar el horario de
 * un 276 en particular sigue siendo trabajador por trabajador, desde
 * turnos.configuracion (ConfiguracionTurnoForm) — esta pantalla no lo
 * hace por lotes.
 *
 * Trabajador NO usa esta pantalla — ve la vista individual
 * (CalendarioIndividualIndex). El Admin tampoco la abre por su cuenta
 * (no tiene equipo propio), pero SÍ puede verla "como" un jefe
 * concreto: ProgramacionAdmin la incrusta con $jefeId y aquí se
 * calcula el equipo de ese jefe (con su mismo alcance y sus filas de
 * solo lectura). Lo que se guarda queda a nombre del admin (actor).
 */
#[Layout('layouts.app')]
#[Title('Calendario de turnos — mi equipo')]
class CalendarioEquipoIndex extends Component
{
    public int $anio;

    public int $mes;

    /**
     * Jefe cuyo equipo se muestra. Solo lo puede fijar un admin (ver
     * mount/jefeVista); null = el propio usuario autenticado. Locked:
     * el navegador no puede cambiarlo.
     */
    #[Locked]
    public ?int $jefeId = null;

    /** Se incrementa al cambiar de mes o guardar para reiniciar el estado Alpine de la grilla. */
    #[Locked]
    public int $version = 0;

    /** @var array<int, string> */
    #[Locked]
    public array $advertencias = [];

    #[Locked]
    public ?string $mensaje = null;

    public function mount(?int $jefeId = null): void
    {
        if ($jefeId !== null) {
            abort_unless(auth()->user()?->hasRole('admin'), 403);
            $this->jefeId = $jefeId;
        }

        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    /**
     * Usuario cuyo equipo se muestra: el autenticado, o —solo si es
     * admin— el jefe elegido. Se revalida en cada acción porque las
     * llamadas a métodos de Livewire no re-evalúan el middleware.
     */
    private function jefeVista(): User
    {
        if ($this->jefeId === null) {
            return auth()->user();
        }

        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return User::findOrFail($this->jefeId);
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
        [$trabajadores, , $soloLectura] = app(EquipoDelJefeService::class)->para($this->jefeVista());

        return $trabajadores
            ->filter(fn (User $t) => $t->regimen === '728' && $t->activo && ! in_array($t->id, $soloLectura, true))
            ->keyBy('id');
    }

    /**
     * Trabajadores 276 del equipo que este usuario puede tocar (los de solo lectura
     * no entran). Se resuelve en cada acción: nunca se confía en lo que vio el navegador.
     */
    private function equipo276(): Collection
    {
        [$trabajadores, , $soloLectura] = app(EquipoDelJefeService::class)->para($this->jefeVista());

        return $trabajadores
            ->filter(fn (User $t) => $t->regimen === '276' && $t->activo && ! in_array($t->id, $soloLectura, true))
            ->keyBy('id');
    }

    /**
     * Asigna al trabajador 276 el horario ordinario global configurado por
     * el Admin (HORARIO_ORDINARIO_DIAS_LABORABLES) con un solo clic, sin
     * necesitar el formulario individual. Calcula automáticamente dias_trabajo
     * y dias_descanso a partir de los días laborables y ancla al lunes de la
     * semana actual.
     */
    public function cargarHorario276(GeneradorTurnoMensualService $generador, int $userId): void
    {
        $equipo276 = $this->equipo276();
        $trabajador = $equipo276->get($userId);

        abort_if($trabajador === null, 403);

        $diasLaborables = array_map(
            'intval',
            array_filter(array_map('trim', explode(
                ',',
                (string) Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5')
            )))
        );

        // Calcula dias_descanso a partir de los días NO laborables de la semana.
        // PatronDescansoSemanal espera los días de DESCANSO (ISO 1–7).
        $todosLosDias = [1, 2, 3, 4, 5, 6, 7];
        $diasDescansoIso = array_values(array_diff($todosLosDias, $diasLaborables));

        // Ancla: lunes de la semana actual (o hoy si no hay días de descanso consecutivos simples).
        $referencia = now()->startOfWeek();

        $resultado = $diasDescansoIso !== []
            ? PatronDescansoSemanal::anclaYCiclo($diasDescansoIso, $referencia)
            : null;

        $diasTrabajo = $resultado['dias_trabajo'] ?? count($diasLaborables);
        $diasDescanso = $resultado['dias_descanso'] ?? (7 - count($diasLaborables));
        $fechaAncla = $resultado ? $resultado['fecha_ancla'] : $referencia;

        $generador->cargarConfiguracion(
            trabajador: $trabajador,
            turno: ConfiguracionTurno::TURNO_276,
            fechaAncla: $fechaAncla,
            actor: auth()->user(),
            diasTrabajo: $diasTrabajo,
            diasDescanso: $diasDescanso,
        );

        $this->mensaje = 'Horario ordinario cargado para '.$trabajador->nombre_completo.'.';
        $this->version++;
    }

    public function render(): View
    {
        $jefe = $this->jefeVista();

        [$trabajadores, $esJefeDeArea, $soloLectura] = app(EquipoDelJefeService::class)->para($jefe);

        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();
        $dias = collect(range(1, $finMes->day));
        $fechas = collect(range(1, $finMes->day))->map(fn (int $d) => $inicioMes->copy()->day($d));

        $diasHabiles = app(CalculadorDiasHabiles::class);
        $noLaborables = $fechas
            ->filter(fn (Carbon $f) => ! $diasHabiles->esDiaLaborable($f))
            ->map(fn (Carbon $f) => $f->toDateString())
            ->values()
            ->all();

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

        $equipo276 = $this->equipo276();
        $configs276 = ConfiguracionTurno::whereIn('user_id', $equipo276->keys())->get()->keyBy('user_id');

        return view('livewire.turnos.calendario-equipo-index', [
            'jefe' => $jefe,
            'vistaAdmin' => $this->jefeId !== null,
            'trabajadores' => $trabajadores,
            'esJefeDeArea' => $esJefeDeArea,
            'soloLectura' => $soloLectura,
            'idsJefes' => app(EquipoDelJefeService::class)->idsDeJefes()->all(),
            'dias' => $dias,
            'fechas' => $fechas,
            'inicioMes' => $inicioMes,
            'turnosPorUsuario' => $turnosPorUsuario,
            'hoy' => now(),
            'uids728' => $equipo728->map(fn (User $t) => (string) $t->id)->values()->all(),
            'fechasIso' => $fechas->map(fn (Carbon $f) => $f->toDateString())->values()->all(),
            'diasIniciales' => (object) $diasIniciales,
            'horas' => $horas,
            'equipo276' => $equipo276,
            'configs276' => $configs276,
            'diasLaborables276' => $this->diasLaborables276(),
            'noLaborables' => $noLaborables,
        ]);
    }

    /**
     * Días hábiles del horario ordinario (1=lunes .. 7=domingo), tal
     * como los dejó el Admin en Configuraciones
     * (HORARIO_ORDINARIO_DIAS_LABORABLES) — puramente informativo,
     * para que el Jefe vea de un vistazo qué días se trabaja sin tener
     * que ir a Configuraciones. El Jefe no puede editarlos desde aquí.
     *
     * @return array<int, int>
     */
    private function diasLaborables276(): array
    {
        return array_map(
            'intval',
            array_filter(array_map('trim', explode(
                ',',
                (string) Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5')
            )))
        );
    }
}