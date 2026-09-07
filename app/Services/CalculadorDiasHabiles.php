<?php

namespace App\Services;

use App\Models\Feriado;
use Carbon\Carbon;

/**
 * Calcula plazos saltando sábados, domingos y feriados cargados por el
 * admin en /admin/feriados. Usado por:
 * - Sustento de Salud: fecha_limite = hora del retorno + 48h hábiles.
 * - Subsanación de Emergencia observada: 15 días hábiles.
 * Sin este servicio, la tabla `feriados` no tiene ningún efecto real.
 */
class CalculadorDiasHabiles
{
    public function esHabil(Carbon $fecha): bool
    {
        return ! $fecha->isWeekend() && ! $this->esFeriado($fecha);
    }

    public function esFeriado(Carbon $fecha): bool
    {
        return Feriado::whereDate('fecha', $fecha->toDateString())->exists();
    }

    /**
     * Suma N días hábiles completos a partir de una fecha, saltando
     * fines de semana y feriados. Usado para la subsanación de
     * Emergencia (15 días hábiles).
     */
    public function agregarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $fecha = $desde->copy();

        while ($dias > 0) {
            $fecha->addDay();

            if ($this->esHabil($fecha)) {
                $dias--;
            }
        }

        return $fecha;
    }

    /**
     * Suma N horas hábiles a partir de una fecha/hora, contando solo
     * horas dentro de días hábiles (24h por día hábil, ya que el flujo
     * no acota el plazo a un horario de oficina). Usado para el
     * sustento de Salud (48h hábiles).
     */
    public function agregarHorasHabiles(Carbon $desde, int $horas): Carbon
    {
        $fecha = $desde->copy();
        $horasRestantes = $horas;

        while ($horasRestantes > 0) {
            $fecha->addHour();

            if ($this->esHabil($fecha)) {
                $horasRestantes--;
            }
        }

        return $fecha;
    }
}
