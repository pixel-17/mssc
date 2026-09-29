<?php

namespace App\Actions\Papeleta;

use App\Models\Turno;
use App\Models\User;
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
 * - Decisor inactivo (dado de baja): nunca disponible, sin importar
 *   régimen — no debería llegar aquí gracias al guardrail de
 *   desactivación (ver User::esJefeTitularDeAlgunTurno /
 *   UsuarioAdminIndex::desactivar), pero se chequea igual por si
 *   queda un jefe_inmediato_id fotografiado de antes de ese guardrail.
 * - Decisor 728 (rotativo): disponible las 24 h (no tiene horario
 *   ordinario), EXCEPTO en su día de descanso: si tiene cargada una fila
 *   de descanso para la fecha y no está dentro de un turno que sigue
 *   corriendo (p. ej. una Noche que empezó ayer y termina a las 06:00),
 *   no puede decidir ahora. Sin programación cargada se asume disponible
 *   (no se puede afirmar que descansa).
 * - Decisor 276 (ordinario): disponible solo dentro de la ventana de
 *   HorarioOrdinarioService (que ya excluye días no laborables y feriados).
 * - Decisor sin régimen 276/728: nunca disponible.
 * - Sin decisor (null, p. ej. el tope del organigrama sin jefatura):
 *   nunca disponible — no hay a quién esperar.
 */
class DecisorDisponibleService
{
    public function __construct(
        private HorarioOrdinarioService $horarioOrdinario,
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

        if (! $decisor->activo) {
            return false;
        }

        // Régimen explícito: un decisor sin régimen (null u otro valor) no
        // se asume disponible ni se trata como 276 por descarte.
        return match ($decisor->regimen) {
            '728' => ! $this->estaDeDescanso($decisor, $momento),
            // estaDentroDeVentana() ya descarta días no laborables y feriados.
            '276' => $this->horarioOrdinario->estaDentroDeVentana($momento),
            default => false,
        };
    }

    /**
     * ¿El decisor 728 descansa en la fecha del momento y no está dentro de
     * un turno vigente? Turno::vigenteParaUsuario() ya considera el cruce
     * de medianoche, así que un turno Noche de ayer que sigue corriendo
     * cuenta como "en turno" aunque hoy figure descanso.
     */
    private function estaDeDescanso(User $decisor, Carbon $momento): bool
    {
        if (Turno::vigenteParaUsuario($decisor->id, $momento) !== null) {
            return false;
        }

        return Turno::where('user_id', $decisor->id)
            ->whereDate('fecha', $momento->toDateString())
            ->where('es_descanso', true)
            ->exists();
    }
}
