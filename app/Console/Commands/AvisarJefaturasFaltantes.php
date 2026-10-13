<?php

namespace App\Console\Commands;

use App\Services\AlertaJefaturaService;
use Illuminate\Console\Command;

/**
 * Corrida periódica de AlertaJefaturaService::avisarFaltantesEnTodoElOrganigrama():
 * detecta unidades con gente cuyo jefe falta o está dado de baja (p. ej. se
 * dejó la jefatura vacante a propósito al dar de baja al jefe).
 */
class AvisarJefaturasFaltantes extends Command
{
    protected $signature = 'jefaturas:avisar-faltantes';

    protected $description = 'Avisa a admin y Jefe de Área de las unidades que se quedaron sin jefe activo.';

    public function handle(AlertaJefaturaService $alerta): int
    {
        $avisos = $alerta->avisarFaltantesEnTodoElOrganigrama();

        $this->info("Avisos de jefatura faltante disparados: {$avisos}.");

        return self::SUCCESS;
    }
}
