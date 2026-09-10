<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario de prueba con rol "admin". Solo administra catálogos vía
 * Filament (unidades orgánicas, sedes, motivos, horario RRHH); nunca
 * decide papeletas (ver RoleSeeder).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@mssc.test'],
            [
                'name' => 'Ana',
                'apellido' => 'Administradora',
                'dni' => '10000001',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
