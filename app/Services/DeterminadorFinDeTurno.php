<?php

namespace App\Services;

use App\Models\Papeleta;

/**
 * Extraído de ProcesarVencimientosPapeletas para que
 * ProcesarAbandonoNoMarcado use exactamente el mismo criterio de "fin
 * de turno/día" sin duplicar la lógica de régimen 276/728.
 */
class DeterminadorFinDeTurno
{
    public function __construct(private HorarioOrdinarioService $horarioOrdinario) {}

    public function yaTermino(Papeleta $papeleta): bool
    {
        if ($papeleta->regimen === '728') {
            // 728 no tiene ventana de horario: el "fin de día" se toma
            // como el cierre del día operativo a medianoche.
            return now()->toDateString() > $papeleta->dia_operativo->toDateString();
        }

        // 276: contra el horario único global, ya no la fila diaria de Turno.
        return $this->horarioOrdinario->yaTerminoElDia($papeleta->dia_operativo);
    }
}
