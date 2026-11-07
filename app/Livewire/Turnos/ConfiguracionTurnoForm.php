<?php

namespace App\Livewire\Turnos;

use App\Models\Configuracion;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
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

    /**
     * true cuando se usa embebido (p. ej. dentro de PersonaDrawer, en el
     * organigrama): sin título de página, sin el layout de pantalla completa
     * y, al guardar/cancelar, avisa con un evento en vez de redirigir a
     * turnos.index (#[Layout] no aplica solo cuando se incluye con
     * <livewire:.../> dentro de otro componente).
     */
    #[Locked]
    public bool $embebido = false;

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

    /**
     * Desde que ningún régimen (276 incluido) tiene horario global de
     * respaldo, un trabajador sin turno vigente cargado para hoy no
     * puede crear NINGUNA papeleta (ver
     * CrearPapeletaAction::resolverTurnoActivo). Quien programa el
     * turno debe verlo de inmediato al entrar a esta pantalla, así
     * que se calcula una sola vez en mount() con la misma consulta
     * que usa esa Action (Turno::vigenteParaUsuario).
     */
    #[Locked]
    public bool $sinTurnoVigenteHoy = false;

    public function mount(User $trabajador, bool $embebido = false): void
    {
        abort_unless((bool) auth()->user()?->puedeGestionarTurnoDe($trabajador), 403);
        abort_unless($trabajador->regimen === '276', 403, 'Esta pantalla es solo para régimen 276; el 728 se programa día por día en turnos.programacion.');

        $this->trabajador = $trabajador;
        $this->embebido = $embebido;

        $this->sinTurnoVigenteHoy = Turno::vigenteParaUsuario($trabajador->id) === null;

        $config = ConfiguracionTurno::where('user_id', $trabajador->id)->first();

        if ($trabajador->regimen === '276') {
            // Para 276 los días los define el admin globalmente. Se
            // calculan aquí solo para la vista previa: lo que de verdad
            // se guarda lo vuelve a calcular e impone
            // GeneradorTurnoMensualService::cargarConfiguracion(), así
            // que aunque estos valores se manipulen en el cliente no hay
            // forma de terminar con un ciclo manual para un 276.
            $ciclo = GeneradorTurnoMensualService::cicloOrdinario276();
            $this->turno = ConfiguracionTurno::TURNO_276;
            $this->fechaAncla = $ciclo['fecha_ancla']->toDateString();
            $this->diasTrabajo = $ciclo['dias_trabajo'];
            $this->diasDescanso = $ciclo['dias_descanso'];
        } elseif ($config) {
            $this->turno = $config->turno;
            $this->fechaAncla = $config->fecha_ancla->toDateString();
            $this->diasTrabajo = $config->dias_trabajo;
            $this->diasDescanso = $config->dias_descanso;
        } else {
            $opciones = ConfiguracionTurno::turnosValidosPara($trabajador);
            $this->turno = count($opciones) === 1 ? $opciones[0] : '';
            $this->fechaAncla = now()->toDateString();
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'turno' => ['required', 'in:'.implode(',', ConfiguracionTurno::turnosValidosPara($this->trabajador))],
            'fechaAncla' => ['required', 'date'],
            'diasTrabajo' => ['required', 'integer', 'min:1', 'max:30'],
            'diasDescanso' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    /** @var array<string, string> */
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
        abort_unless((bool) auth()->user()?->puedeGestionarTurnoDe($this->trabajador), 403);

        $datos = $this->validate();

        /** @var User $actor */
        $actor = auth()->user();

        $generador->cargarConfiguracion(
            trabajador: $this->trabajador,
            turno: $datos['turno'],
            fechaAncla: Carbon::parse($datos['fechaAncla']),
            actor: $actor,
            diasTrabajo: $datos['diasTrabajo'],
            diasDescanso: $datos['diasDescanso'],
        );

        // Embebido (p. ej. en el organigrama): nada de redirect, el contenedor
        // (PersonaDrawer) escucha este evento y cierra el panel inline.
        if ($this->embebido) {
            $this->dispatch('turno-guardado', trabajadorId: $this->trabajador->id);

            return;
        }

        session()->flash('mensaje', 'Configuración de turno cargada. Se generó el horario del mes en curso.');

        $this->redirectRoute('turnos.index', navigate: false);
    }

    /** Solo tiene sentido embebido: en la pantalla completa "Cancelar" es un link directo a turnos.index. */
    public function cancelar(): void
    {
        if ($this->embebido) {
            $this->dispatch('turno-cancelado');
        }
    }

    /**
     * Todos los días del mes en curso (que es el mes que de verdad se
     * genera al guardar, ver guardar() más arriba), marcados
     * trabajo/descanso con la MISMA cuenta que se va a guardar
     * (GeneradorTurnoMensualService::esDescansoEnFecha). Así el admin
     * ve el resultado real antes de guardar, sin reimplementar la
     * lógica del ciclo en Blade/JS.
     *
     * @return array<int, array{fecha: string, etiqueta: string, descanso: bool, yaProgramado: bool}>|null
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

        $inicio = now()->startOfMonth();
        $fin = now()->endOfMonth();
        $diasEnMes = $inicio->diffInDays($fin) + 1;

        // Fechas que YA tienen un registro guardado en `turnos` para este
        // trabajador: se pintan distinto (azulado) en la vista previa para
        // que el admin/jefe note que esos días ya están programados de
        // verdad, no son solo un cálculo del ciclo que todavía no se guardó.
        $fechasYaProgramadas = Turno::where('user_id', $this->trabajador->id)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->pluck('fecha')
            ->map(fn ($fecha) => Carbon::parse($fecha)->toDateString())
            ->all();

        return collect(range(0, $diasEnMes - 1))
            ->map(function (int $i) use ($inicio, $ancla, $fechasYaProgramadas) {
                $dia = $inicio->copy()->addDays($i);

                return [
                    'fecha' => $dia->toDateString(),
                    'etiqueta' => ucfirst($dia->translatedFormat('D d')),
                    'descanso' => GeneradorTurnoMensualService::esDescansoEnFecha($ancla, $dia, $this->diasTrabajo, $this->diasDescanso),
                    'yaProgramado' => in_array($dia->toDateString(), $fechasYaProgramadas, true),
                ];
            })
            ->all();
    }

    public function render(): View
    {
        $diasLaborables276 = null;

        if ($this->trabajador->regimen === '276') {
            $laborables = array_filter(array_map('intval', array_map('trim', explode(
                ',',
                (string) Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5')
            ))));

            $nombres = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
            $diasLaborables276 = array_map(fn (int $d) => $nombres[$d] ?? $d, array_values($laborables));
        }

        return view('livewire.turnos.configuracion-turno-form', [
            'opcionesTurno' => ConfiguracionTurno::turnosValidosPara($this->trabajador),
            'previsualizacion' => $this->previsualizacion(),
            'diasLaborables276' => $diasLaborables276,
        ]);
    }
}