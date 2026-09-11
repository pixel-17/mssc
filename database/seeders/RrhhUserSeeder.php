<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario de prueba con rol "rrhh". Ve las papeletas ya autorizadas por
 * los jefes y registra la salida/retorno real del trabajador (pasos 6-7
 * del flujo de papeletas).
 *
 * RRHH se arma DESIGNANDO trabajadores de régimen 276 al rol 'rrhh',
 * ubicados en la unidad orgánica "Oficina de Recursos Humanos" (ya
 * existe en el organigrama, sembrada por UnidadOrganicaSeeder). El
 * "horario de RRHH" ya no se escribe a mano (ver RrhhHorarioService):
 * se deriva de que exista al menos un trabajador con este rol, con el
 * horario ordinario fijo de 276.
 */
class RrhhUserSeeder extends Seeder
{
    public function run(): void
    {
        $areaRrhh = UnidadOrganica::where('nombre', 'Oficina de Recursos Humanos')->firstOrFail();

        $rrhh = User::updateOrCreate(
            ['email' => 'rrhh@mssc.test'],
            [
                'name' => 'Rosa',
                'apellido' => 'Recursos Humanos',
                'dni' => '10000002',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '276',
                'unidad_organica_id' => $areaRrhh->id,
            ]
        );

        if (! $rrhh->hasRole('rrhh')) {
            $rrhh->assignRole('rrhh');
        }
    }
}
