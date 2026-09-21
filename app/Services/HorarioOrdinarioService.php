<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Support\Carbon;

/**
 * Horario único y global para TODO trabajador en régimen 276 (ordinario).
 *
 * Reemplaza la fila diaria por trabajador en `turnos`: con ~500
 * trabajadores 276 era insostenible que el admin cargara un turno por
 * persona por día. Ahora es un solo horario (días laborables + hora
 * inicio/fin) editable desde Configuraciones (Filament), igual para
 * todos los 276.
 *
 * Los 728 (rotativo) NO usan este servicio: siguen con `turnos` como
 * registro opcional/informativo. Por defecto no hay ventana que
 * bloquee la creación de papeleta para 728; ver MODO_ESTRICTO_728 en
 * CrearPapeletaAction para la excepción (interruptor global, solo
 * Admin lo cambia).
 */
class HorarioOrdinarioService
{
    /**
     * Valores por defecto ÚNICOS del horario ordinario (mismos que siembra
     * ConfiguracionSeeder). Antes cada servicio tenía su propio fallback
     * (17:00 aquí, 16:15 en RrhhHorarioService) y, si faltaba la clave en
     * `configuraciones`, la ventana de creación y el criterio de RRHH
     * quedaban desalineados.
     */
    public const HORA_INICIO_DEFECTO = '07:45';

    public const HORA_FIN_DEFECTO = '16:15';

    /**
     * ¿El momento cae en día laborable y entre la hora de inicio y el fin
     * (ambos inclusive, minuto completo)? Compara instantes, no strings
     * H:i: con strings, un valor sin cero inicial en Configuraciones
     * ("8:00") rompía la comparación silenciosamente.
     */
    public function estaDentroDeVentana(?Carbon $momento = null): bool
    {
        $momento ??= now();

        if (! $this->esDiaLaborable($momento)) {
            return false;
        }

        [$horaInicio] = $this->ventanaDeHoy();

        $inicio = $momento->copy()->setTimeFromTimeString($horaInicio)->startOfMinute();

        return $momento->greaterThanOrEqualTo($inicio) && $momento->lessThanOrEqualTo($this->finDelDia($momento));
    }

    /**
     * Instante en que termina el horario ordinario en la fecha dada. Se
     * incluye todo el minuto de HORA_FIN (hasta :59), igual que
     * estaDentroDeVentana(), que compara solo H:i con <=.
     */
    public function finDelDia(Carbon $fecha): Carbon
    {
        [, $horaFin] = $this->ventanaDeHoy();

        return $fecha->copy()->setTimeFromTimeString($horaFin)->endOfMinute();
    }

    /**
     * Usado por DeterminadorFinDeTurno: ¿ya pasó la hora de salida del
     * horario ordinario para la fecha operativa de la papeleta?
     */
    public function yaTerminoElDia(Carbon $fecha): bool
    {
        return now()->greaterThan($this->finDelDia($fecha));
    }

    private function esDiaLaborable(Carbon $momento): bool
    {
        $dias = array_map(
            'trim',
            explode(',', (string) Configuracion::valorDe('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5'))
        );

        return in_array((string) $momento->isoWeekday(), $dias, true);
    }

    /**
     * @return array{0: string, 1: string} [hora_inicio, hora_fin] en formato H:i
     */
    private function ventanaDeHoy(): array
    {
        return [
            (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_INICIO', self::HORA_INICIO_DEFECTO),
            (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_FIN', self::HORA_FIN_DEFECTO),
        ];
    }
}
