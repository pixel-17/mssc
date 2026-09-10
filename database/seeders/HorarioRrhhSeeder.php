<?php

namespace Database\Seeders;

use App\Models\HorarioRrhh;
use Illuminate\Database\Seeder;

/**
 * Horario único de RRHH para toda la municipalidad: L-V 07:45-16:15
 * activo, sábado y domingo inactivos. Editable después desde
 * HorarioRrhhResource (solo valores, nunca filas nuevas).
 */
class HorarioRrhhSeeder extends Seeder
{
    public function run(): void
    {
        for ($dia = 0; $dia <= 6; $dia++) {
            $esLaborable = $dia >= 1 && $dia <= 5; // 1=lunes ... 5=viernes

            HorarioRrhh::updateOrCreate(
                ['dia_semana' => $dia],
                [
                    'hora_inicio' => '07:45:00',
                    'hora_fin' => '16:15:00',
                    'activo' => $esLaborable,
                ]
            );
        }
    }
}
