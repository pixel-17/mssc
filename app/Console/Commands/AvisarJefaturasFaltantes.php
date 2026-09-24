<?php

namespace App\Console\Commands;

use App\Services\AlertaJefaturaService;
use Illuminate\Console\Command;

/**
 * Corrida periódica de AlertaJefaturaService::avisarFaltantesEnTodoElOrganigrama():
 * detecta huecos de cobertura por turno (MANANA/TARDE/NOCHE) que
 * aparecen SIN que nadie esté dando de alta a un trabajador justo en
 * ese momento — p. ej. se desactiva al único jefe titular de un
 * turno (ver User::esJefeTitularDeAlgunTurno, que ya bloquea la
 * desactivación en el flujo normal, pero no cubre bajas hechas fuera
 * de ese camino ni reasignaciones manuales de jefes_turno).
 */
class AvisarJefaturasFaltantes extends Command
{
    protected $signature = 'jefaturas:avisar-faltantes';

    protected $description = 'Avisa a admin y Jefe de Área de las unidades cuyo turno (MANANA/TARDE/NOCHE) se quedó sin jefe inmediato activo.';

    public function handle(AlertaJefaturaService $alerta): int
    {
        $avisos = $alerta->avisarFaltantesEnTodoElOrganigrama();

        $this->info("Avisos de jefatura faltante disparados: {$avisos}.");

        return self::SUCCESS;
    }
}
