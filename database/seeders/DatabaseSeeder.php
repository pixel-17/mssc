<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * A propósito, mínimo: solo los roles (sin esto assignRole('admin')
     * falla) y el usuario Admin. El resto de los seeders (organigrama,
     * sedes, motivos, configuración, usuarios de prueba por rol, etc.)
     * siguen existiendo como clases — se corren a mano o desde los tests
     * cuando hagan falta — pero no se disparan solos en cada db:seed.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}