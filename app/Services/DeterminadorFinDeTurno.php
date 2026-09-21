<?php

namespace App\Services;

use App\Models\Papeleta;

/**
 * Extraído de ProcesarVencimientosPapeletas para que
 * ProcesarAbandonoNoMarcado use exactamente el mismo criterio de "fin
 * de turno/día" sin duplicar la lógica de régimen 276/728.
 *
 * El fin se decide UNA vez, al crear la papeleta (papeletas.fin_turno_at,
 * ver CrearPapeletaAction), y desde ahí ambos jobs solo comparan contra
 * ese instante. Así el turno Noche de 728 (22:00-06:00) ya no vence ni se
 * marca como abandono a medianoche, y una edición posterior del turno no
 * cambia el criterio de una papeleta ya creada.
 */
class DeterminadorFinDeTurno
{
    public function __construct(private HorarioOrdinarioService $horarioOrdinario) {}

    public function yaTermino(Papeleta $papeleta): bool
    {
        if ($papeleta->fin_turno_at !== null) {
            return now()->greaterThan($papeleta->fin_turno_at);
        }

        return $this->yaTerminoLegado($papeleta);
    }

    /**
     * Papeletas anteriores a fin_turno_at que la migración no pudo
     * completar (728 sin turno cargado para su día operativo).
     */
    private function yaTerminoLegado(Papeleta $papeleta): bool
    {
        if ($papeleta->regimen === '728') {
            // Sin turno no hay hora de fin: el "fin de día" se toma como
            // el cierre del día operativo a medianoche.
            return now()->toDateString() > $papeleta->dia_operativo->toDateString();
        }

        // 276: contra el horario único global, ya no la fila diaria de Turno.
        return $this->horarioOrdinario->yaTerminoElDia($papeleta->dia_operativo);
    }
}
