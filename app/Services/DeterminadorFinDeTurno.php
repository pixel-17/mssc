<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\Turno;

/**
 * Extraído de ProcesarVencimientosPapeletas para que
 * ProcesarAbandonoNoMarcado use exactamente el mismo criterio de "fin
 * de turno/día" sin duplicar la lógica de régimen CAS/728.
 */
class DeterminadorFinDeTurno
{
    public function yaTermino(Papeleta $papeleta): bool
    {
        if ($papeleta->regimen === '728') {
            // 728 no tiene ventana de horario: el "fin de día" se toma
            // como el cierre del día operativo a medianoche.
            return now()->toDateString() > $papeleta->dia_operativo->toDateString();
        }

        $turno = Turno::where('user_id', $papeleta->trabajador_id)
            ->whereDate('fecha', $papeleta->dia_operativo)
            ->first();

        if (! $turno || ! $turno->hora_fin) {
            // Sin turno cargado, se usa fin de día como respaldo.
            return now()->toDateString() > $papeleta->dia_operativo->toDateString();
        }

        return now()->toDateString() > $papeleta->dia_operativo->toDateString()
            || now()->format('H:i:s') > $turno->hora_fin;
    }
}
