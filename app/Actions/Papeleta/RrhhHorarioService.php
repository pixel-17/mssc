<?php

namespace App\Actions\Papeleta;

use App\Models\User;
use App\Services\CalculadorDiasHabiles;
use App\Services\HorarioOrdinarioService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Único punto de verdad sobre si RRHH está "en horario" ahora mismo.
 * Usado en AprobarJefeAction (Paso 2) y por el comando de vencimientos
 * para armar la bandeja de revisión post-hoc (Paso 4).
 *
 * Antes esto se leía de una tabla `horarios_rrhh` editada a mano por el
 * admin, duplicando el mismo horario que ya existe parametrizado para
 * el régimen 276 (Configuracion::HORARIO_ORDINARIO_*). Ahora RRHH está
 * "en horario" cuando se cumplen las tres cosas:
 *
 *  1. Es un momento dentro del horario ordinario de 276 (días laborables
 *     y horas de HorarioOrdinarioService: mismas claves que ya usa el
 *     resto del sistema para validar la ventana de creación de papeletas).
 *  2. El día no es un feriado cargado en /admin/feriados.
 *  3. Existe al menos un trabajador con rol 'rrhh' ACTIVO — RRHH se arma
 *     DESIGNANDO trabajadores de régimen 276 (rol 'rrhh'), no escribiendo
 *     un horario suelto sin relación con el personal real. Si no hay nadie
 *     designado y activo, RRHH nunca está "en horario".
 */
class RrhhHorarioService
{
    public function __construct(
        private HorarioOrdinarioService $horarioOrdinario,
        private CalculadorDiasHabiles $diasHabiles,
    ) {}

    public function estaEnHorarioAhora(): bool
    {
        return $this->estaEnHorario(now());
    }

    public function estaEnHorario(CarbonInterface $momento): bool
    {
        // HorarioOrdinarioService trabaja con Illuminate\Support\Carbon.
        $momento = Carbon::instance($momento);

        if (! $this->hayPersonalRrhhDesignado()) {
            return false;
        }

        if ($this->diasHabiles->esFeriado($momento)) {
            return false;
        }

        return $this->horarioOrdinario->estaDentroDeVentana($momento);
    }

    /**
     * ¿Hay al menos un trabajador activo designado a RRHH (rol 'rrhh')?
     * RRHH se arma asignando el rol a trabajadores de régimen 276,
     * no existe como horario aislado sin personal real detrás. Un usuario
     * desactivado no cuenta: no hay nadie que revise ni decida.
     */
    protected function hayPersonalRrhhDesignado(): bool
    {
        return User::role('rrhh')->where('activo', true)->exists();
    }
}
