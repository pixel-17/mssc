<?php

namespace App\Actions\Papeleta;

use App\Models\User;
use App\Services\CalculadorDiasHabiles;
use App\Services\HorarioOrdinarioService;
use Illuminate\Support\Carbon;

/**
 * ¿Puede este decisor (Jefe Inmediato o Jefe de Área — ambos son User,
 * ver PapeletaPolicy) actuar AHORA sobre una papeleta?
 *
 * Estar asignado en la BD (jefe_inmediato_id resuelto) no implica estar
 * disponible: un decisor 276 fuera de su horario ordinario (o en
 * feriado) no puede aprobar/rechazar/observar en tiempo real. Antes
 * CrearPapeletaAction asumía disponibilidad permanente de quien
 * jefaturasDe() resolviera; con esto, la creación puede saltarlo si no
 * está en condición de decidir — mismo criterio que RrhhHorarioService
 * ya aplica para RRHH, ahora reusable para cualquier decisor.
 *
 * - Decisor 728 (rotativo): siempre disponible. Mismo criterio que ya
 *   usa CrearPapeletaAction para no bloquear por horario la creación de
 *   papeletas de un 728.
 * - Decisor 276 (ordinario): disponible solo dentro de la ventana de
 *   HorarioOrdinarioService y si el día no es feriado.
 * - Sin decisor (null, p. ej. el tope del organigrama sin jefatura):
 *   nunca disponible — no hay a quién esperar.
 */
class DecisorDisponibleService
{
    public function __construct(
        private HorarioOrdinarioService $horarioOrdinario,
        private CalculadorDiasHabiles $diasHabiles,
    ) {}

    public function estaDisponibleAhora(?User $decisor): bool
    {
        return $this->estaDisponible($decisor, Carbon::now());
    }

    public function estaDisponible(?User $decisor, Carbon $momento): bool
    {
        if (! $decisor) {
            return false;
        }

        if ($decisor->regimen !== '276') {
            return true;
        }

        if ($this->diasHabiles->esFeriado($momento)) {
            return false;
        }

        return $this->horarioOrdinario->estaDentroDeVentana($momento);
    }
}
