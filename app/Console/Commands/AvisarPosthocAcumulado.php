<?php

namespace App\Console\Commands;

use App\Services\AlertaPosthocService;
use Illuminate\Console\Command;

/**
 * Corrida periódica de AlertaPosthocService::evaluar(): avisa a RRHH (y a
 * admin) cuando la cola de revisión post-hoc supera el umbral de cantidad
 * o de antigüedad configurado. Ver el servicio para las reglas completas.
 */
class AvisarPosthocAcumulado extends Command
{
    protected $signature = 'papeletas:avisar-posthoc-acumulado';

    protected $description = 'Avisa a RRHH y admin cuando las revisiones post-hoc pendientes se acumulan o envejecen sin atenderse.';

    public function handle(AlertaPosthocService $alerta): int
    {
        $this->info($alerta->evaluar()
            ? 'Alerta de post-hoc acumulado enviada.'
            : 'Sin alerta: la cola post-hoc está dentro de los umbrales (o ya se avisó hoy).');

        return self::SUCCESS;
    }
}
