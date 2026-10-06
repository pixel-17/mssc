<?php

namespace App\Services;

use App\Models\Configuracion;
use Carbon\Carbon;

/**
 * ÚNICA definición de "día hábil" del sistema: día laborable según
 * Configuraciones (HORARIO_ORDINARIO_DIAS_LABORABLES). Los feriados no se
 * consideran. Lo usan:
 * - HorarioOrdinarioService (ventana de 276, disponibilidad de RRHH y
 *   de decisores 276).
 * - Los plazos en horas/días hábiles (sustento de Salud: 48h hábiles).
 * Antes los plazos asumían sábado y domingo como no hábiles y la
 * ventana de 276 leía los días de Configuraciones: si el admin cambiaba
 * los días laborables, ambos criterios se desalineaban.
 */
class CalculadorDiasHabiles
{
    public function esHabil(Carbon $fecha): bool
    {
        return $this->esDiaLaborable($fecha);
    }

    /**
     * ¿El día de la semana está entre los laborables configurados
     * (ISO: 1 = lunes ... 7 = domingo)?
     */
    public function esDiaLaborable(Carbon $fecha): bool
    {
        $dias = array_map(
            'trim',
            explode(',', (string) Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5'))
        );

        return in_array((string) $fecha->isoWeekday(), $dias, true);
    }

    /**
     * Suma N días hábiles completos a partir de una fecha, saltando
     * los días no laborables.
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
