<?php

namespace App\Livewire\Turnos;

use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla para que Admin o Jefe (Inmediato/Área, ver
 * User::puedeGestionarTurnoDe) generen el horario del mes en curso de
 * un trabajador de régimen 276. El 728 NO pasa por aquí: se programa
 * día por día en turnos.programacion (ver el abort_unless de mount()
 * más abajo), así que esta clase no tiene ningún ciclo editable ni
 * selector de turno — para 276 el ciclo sale siempre del horario
 * ordinario global (HORARIO_ORDINARIO_DIAS_LABORABLES), nunca de un
 * formulario.
 *
 * Guardar aquí SIEMPRE marca el mes como 'manual' (ver
 * GeneradorTurnoMensualService::cargarConfiguracion) — eso es lo que
 * evita que el comando automático del mes siguiente lo pise mientras
 * sigan sin tocar esta pantalla.
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

    /**
     * Mensaje de éxito tras guardar(). guardar() NO redirige a
     * turnos.index (esa ruta es el catálogo de admin, una pantalla
     * totalmente distinta) ni navega a ningún lado: se queda en esta
     * misma pantalla y repinta el calendario del mes (ver render())
     * con lo que se acaba de generar. Este mensaje es el único
     * feedback visible del guardado.
     */
    public ?string $mensajeExito = null;

    public function mount(User $trabajador, bool $embebido = false): void
    {
        abort_unless((bool) auth()->user()?->puedeGestionarTurnoDe($trabajador), 403);
        abort_unless($trabajador->regimen === '276', 403, 'Esta pantalla es solo para régimen 276; el 728 se programa día por día en turnos.programacion.');

        $this->trabajador = $trabajador;
        $this->embebido = $embebido;

        $this->sinTurnoVigenteHoy = Turno::vigenteParaUsuario($trabajador->id) === null;
    }

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        abort_unless((bool) auth()->user()?->puedeGestionarTurnoDe($this->trabajador), 403);

        $this->mensajeExito = null;

        /** @var User $actor */
        $actor = auth()->user();

        // El ciclo (fecha_ancla/dias_trabajo/dias_descanso) para 276 lo
        // recalcula e impone igual cargarConfiguracion() a partir de
        // HORARIO_ORDINARIO_DIAS_LABORABLES, así que lo que se mande
        // acá no importa — se pasa el cómputo actual solo para dejar
        // un valor consistente en ese primer guardado.
        $ciclo = GeneradorTurnoMensualService::cicloOrdinario276();

        $generador->cargarConfiguracion(
            trabajador: $this->trabajador,
            turno: ConfiguracionTurno::TURNO_276,
            fechaAncla: $ciclo['fecha_ancla'],
            actor: $actor,
            diasTrabajo: $ciclo['dias_trabajo'],
            diasDescanso: $ciclo['dias_descanso'],
        );

        // Embebido (p. ej. en el organigrama): nada de redirect, el contenedor
        // (PersonaDrawer) escucha este evento y cierra el panel inline.
        if ($this->embebido) {
            $this->dispatch('turno-guardado', trabajadorId: $this->trabajador->id);

            return;
        }

        // Pantalla completa: ya NO se redirige a turnos.index (ese nombre
        // de ruta es el catálogo de admin, no este formulario). Se
        // permanece acá; render() vuelve a calcular calendarioDelMes()
        // leyendo los `turnos` recién generados, así que el calendario
        // se repinta solo sin navegar a ningún lado.
        $this->sinTurnoVigenteHoy = Turno::vigenteParaUsuario($this->trabajador->id) === null;
        $this->mensajeExito = 'Configuración de turno cargada. Se generó el horario del mes en curso.';
    }

    /** Solo tiene sentido embebido: en la pantalla completa "Cancelar" es un link directo a turnos.index. */
    public function cancelar(): void
    {
        if ($this->embebido) {
            $this->dispatch('turno-cancelado');
        }
    }

    /**
     * Calendario REAL del mes en curso (que es el mes que de verdad se
     * genera al guardar, ver guardar() más arriba) para $trabajador:
     * misma consulta y misma forma de armar $semanas que
     * CalendarioIndividualIndex::render(), para que esta pantalla se
     * vea y se comporte como ese calendario. No es un cálculo
     * proyectado del ciclo — son los registros `turnos` que ya existen,
     * así que antes de guardar los días están en blanco (sin turno) y
     * justo después de guardar aparecen pintados con el color real de
     * cada turno.
     *
     * @return array{inicioMes: Carbon, semanas: array<int, array<int, int|null>>, turnosPorDia: \Illuminate\Support\Collection<int, Turno>, hoy: Carbon}
     */
    private function calendarioDelMes(): array
    {
        $inicioMes = now()->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $turnosPorDia = Turno::where('user_id', $this->trabajador->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->get()
            ->keyBy(fn (Turno $turno) => $turno->fecha->day);

        $primerDiaSemana = $inicioMes->dayOfWeekIso; // 1 = lunes
        $celdas = array_fill(0, $primerDiaSemana - 1, null);

        for ($dia = 1; $dia <= $finMes->day; $dia++) {
            $celdas[] = $dia;
        }

        while (count($celdas) % 7 !== 0) {
            $celdas[] = null;
        }

        return [
            'inicioMes' => $inicioMes,
            'semanas' => array_chunk($celdas, 7),
            'turnosPorDia' => $turnosPorDia,
            'hoy' => now(),
        ];
    }

    public function render(): View
    {
        return view('livewire.turnos.configuracion-turno-form', $this->calendarioDelMes());
    }
}