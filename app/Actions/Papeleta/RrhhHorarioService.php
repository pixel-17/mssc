<?php

namespace App\Actions\Papeleta;

use App\Models\Configuracion;
use App\Models\User;
use Carbon\Carbon;

/**
 * Único punto de verdad sobre si RRHH está "en horario" ahora mismo.
 * Usado en AprobarJefeAction (Paso 2) y por el comando de vencimientos
 * para armar la bandeja de revisión post-hoc (Paso 4).
 *
 * Antes esto se leía de una tabla `horarios_rrhh` editada a mano por el
 * admin, duplicando el mismo horario que ya existe parametrizado para
 * el régimen 276 (Configuracion::HORARIO_ORDINARIO_*). Ahora se deriva
 * de dos cosas:
 *
 *  1. El horario ordinario de 276 (mismas claves que ya usa el resto
 *     del sistema para validar la ventana de creación de papeletas).
 *  2. Que exista al menos un trabajador con rol 'rrhh' activo — RRHH
 *     se arma DESIGNANDO trabajadores de régimen 276 (rol 'rrhh'),
 *     no escribiendo un horario suelto sin relación con el personal
 *     real. Si no hay nadie designado, RRHH nunca está "en horario".
 */
class RrhhHorarioService
{
    public function estaEnHorarioAhora(): bool
    {
        return $this->estaEnHorario(now());
    }

    public function estaEnHorario(Carbon $momento): bool
    {
        if (! $this->hayPersonalRrhhDesignado()) {
            return false;
        }

        $diasLaborables = array_map(
            'intval',
            explode(',', Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5'))
        );

        if (! in_array($momento->isoWeekday(), $diasLaborables, true)) {
            return false;
        }

        $horaInicio = Configuracion::valorDe('HORARIO_ORDINARIO_HORA_INICIO', '07:45');
        $horaFin = Configuracion::valorDe('HORARIO_ORDINARIO_HORA_FIN', '16:15');
        $horaActual = $momento->format('H:i');

        return $horaActual >= $horaInicio && $horaActual <= $horaFin;
    }

    /**
     * ¿Hay al menos un trabajador designado a RRHH (rol 'rrhh')?
     * RRHH se arma asignando el rol a trabajadores de régimen 276,
     * no existe como horario aislado sin personal real detrás.
     */
    protected function hayPersonalRrhhDesignado(): bool
    {
        return User::role('rrhh')->exists();
    }
}
