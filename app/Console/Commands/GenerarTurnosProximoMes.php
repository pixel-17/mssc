<?php

namespace App\Console\Commands;

use App\Services\GeneradorTurnoMensualService;
use Illuminate\Console\Command;

/**
 * "Si Admin o Jefe no cargan una actualización, se carga la misma
 * configuración para el mes siguiente": corre programado (ver
 * bootstrap/app.php) unos días antes de fin de mes, y por cada
 * trabajador con ConfiguracionTurno vigente, continúa el ciclo hacia
 * el mes que viene si todavía no le cargaron nada para ese mes.
 *
 * No hace nada distinto a lo que haría un Admin cargando manualmente
 * la misma configuración otra vez: reutiliza el mismo servicio, solo
 * que con origen 'automatico' para no pisar una carga manual.
 */
class GenerarTurnosProximoMes extends Command
{
    protected $signature = 'turnos:generar-proximo-mes';

    protected $description = 'Genera el horario del mes siguiente para trabajadores 728/276 cuya configuración de turno no fue actualizada por Admin/Jefe.';

    public function handle(GeneradorTurnoMensualService $generador): int
    {
        $generador->generarProximoMesParaTodos();

        return self::SUCCESS;
    }
}
