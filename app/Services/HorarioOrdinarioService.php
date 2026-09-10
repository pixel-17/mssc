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
 * registro opcional/informativo, sin ventana que bloquee la creación
 * de papeleta (ver CrearPapeletaAction).
 */
class HorarioOrdinarioService
{
    public function estaDentroDeVentana(?Carbon $momento = null): bool
    {
        $momento ??= now();

        if (! $this->esDiaLaborable($momento)) {
            return false;
        }

        [$horaInicio, $horaFin] = $this->ventanaDeHoy();

        return $momento->format('H:i') >= $horaInicio && $momento->format('H:i') <= $horaFin;
    }

    /**
     * Usado por DeterminadorFinDeTurno: ¿ya pasó la hora de salida del
     * horario ordinario para la fecha operativa de la papeleta?
     */
    public function yaTerminoElDia(Carbon $fecha): bool
    {
        $hoy = now();

        if ($hoy->toDateString() > $fecha->toDateString()) {
            return true;
        }

        if ($hoy->toDateString() < $fecha->toDateString()) {
            return false;
        }

        [, $horaFin] = $this->ventanaDeHoy();

        return $hoy->format('H:i') > $horaFin;
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
            (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_INICIO', '08:00'),
            (string) Configuracion::valorDe('HORARIO_ORDINARIO_HORA_FIN', '17:00'),
        ];
    }
}
