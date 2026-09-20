<?php

namespace App\Livewire\Turnos;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Vista individual: calendario mensual clásico con SOLO los datos del
 * propio trabajador. Trabajador ve el suyo (sin parámetro en la
 * ruta); Admin puede ver el de cualquiera (con {trabajador} en la
 * ruta). Ambos en modo lectura: crear/editar el horario se hace
 * siempre desde turnos.configuracion (Admin) o desde la vista de
 * equipo (Jefe) — nunca desde aquí.
 */
#[Layout('layouts.app')]
#[Title('Calendario de turnos')]
class CalendarioIndividualIndex extends Component
{
    #[Locked]
    public User $trabajador;

    public int $anio;

    public int $mes;

    public function mount(?User $trabajador = null): void
    {
        $actor = auth()->user();

        if ($trabajador === null) {
            $this->trabajador = $actor;
        } else {
            abort_unless($actor->hasRole('admin') || $actor->id === $trabajador->id, 403);
            $this->trabajador = $trabajador;
        }

        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    /**
     * Tiempo real: cuando alguien guarda el horario de este trabajador,
     * HorarioActualizado llega por Reverb a su canal privado y Livewire
     * vuelve a renderizar el calendario. Solo se suscribe el propio
     * trabajador: el canal App.Models.User.{id} solo lo autoriza a él
     * (routes/channels.php). Admin viendo el calendario de otro se
     * apoya en el refresco periódico de la vista (wire:poll).
     */
    public function getListeners(): array
    {
        if (auth()->id() !== $this->trabajador->id) {
            return [];
        }

        return [
            "echo-private:App.Models.User.{$this->trabajador->id},HorarioActualizado" => 'refrescar',
        ];
    }

    /** Sin cuerpo a propósito: la petición de Livewire ya vuelve a ejecutar render(). */
    public function refrescar(): void {}

    public function mesAnterior(): void
    {
        $fecha = Carbon::create($this->anio, $this->mes, 1)->subMonthNoOverflow();
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
    }

    public function mesSiguiente(): void
    {
        $fecha = Carbon::create($this->anio, $this->mes, 1)->addMonthNoOverflow();
        $this->anio = $fecha->year;
        $this->mes = $fecha->month;
    }

    public function irAHoy(): void
    {
        $this->anio = now()->year;
        $this->mes = now()->month;
    }

    public function render(): View
    {
        $inicioMes = Carbon::create($this->anio, $this->mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $turnosPorDia = Turno::where('user_id', $this->trabajador->id)
            ->whereBetween('fecha', [$inicioMes->toDateString(), $finMes->toDateString()])
            ->with('sede')
            ->get()
            ->keyBy(fn (Turno $turno) => $turno->fecha->day);

        // Semanas del mes en filas de 7 (Lunes a Domingo), con celdas
        // vacías (null) antes del día 1 y después del último día para
        // completar la grilla clásica de calendario.
        $primerDiaSemana = $inicioMes->dayOfWeekIso; // 1 = lunes
        $celdas = array_fill(0, $primerDiaSemana - 1, null);

        for ($dia = 1; $dia <= $finMes->day; $dia++) {
            $celdas[] = $dia;
        }

        while (count($celdas) % 7 !== 0) {
            $celdas[] = null;
        }

        $semanas = array_chunk($celdas, 7);

        return view('livewire.turnos.calendario-individual-index', [
            'inicioMes' => $inicioMes,
            'semanas' => $semanas,
            'turnosPorDia' => $turnosPorDia,
            'hoy' => now(),
        ]);
    }
}
