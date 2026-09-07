<?php

namespace App\Actions\Papeleta;

use App\Models\HorarioRrhh;
use Carbon\Carbon;

/**
 * Único punto de verdad sobre si RRHH está "en horario" ahora mismo.
 * Usado en AprobarJefeAction (Paso 2) y por el comando de vencimientos
 * para armar la bandeja de revisión post-hoc (Paso 4).
 */
class RrhhHorarioService
{
    public function estaEnHorarioAhora(): bool
    {
        return $this->estaEnHorario(now());
    }

    public function estaEnHorario(Carbon $momento): bool
    {
        $horario = HorarioRrhh::where('dia_semana', $momento->dayOfWeek)
            ->where('activo', true)
            ->first();

        if (! $horario) {
            return false;
        }

        $horaActual = $momento->format('H:i:s');

        return $horaActual >= $horario->hora_inicio && $horaActual <= $horario->hora_fin;
    }
}
