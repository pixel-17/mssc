<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario de prueba con rol "rrhh". Ve las papeletas ya autorizadas por
 * los jefes y registra la salida/retorno real del trabajador (pasos 6-7
 * del flujo de papeletas).
 */
class RrhhUserSeeder extends Seeder
{
    public function run(): void
    {
        $rrhh = User::updateOrCreate(
            ['email' => 'rrhh@mssc.test'],
            [
                'name' => 'Rosa',
                'apellido' => 'Recursos Humanos',
                'dni' => '10000002',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '728',
            ]
        );

        if (! $rrhh->hasRole('rrhh')) {
            $rrhh->assignRole('rrhh');
        }
    }
}
