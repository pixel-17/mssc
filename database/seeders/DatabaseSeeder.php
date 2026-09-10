<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UnidadOrganicaSeeder::class,
            MotivoSeeder::class,
            ConfiguracionSeeder::class,
            HorarioRrhhSeeder::class,

            // Un usuario de prueba por cada rol/perfil real del sistema.
            // El orden importa: Jefe de Área y Jefe Inmediato arman el
            // árbol de jefaturas que Trabajador necesita para escalar.
            AdminUserSeeder::class,
            RrhhUserSeeder::class,
            JefeAreaUserSeeder::class,
            JefeInmediatoUserSeeder::class,
            TrabajadorUserSeeder::class,
        ]);

        // User::factory(10)->create();

        $testUser = User::factory()->create([
            'name' => 'Test',
            'apellido' => 'User',
            'dni' => '00000001',
            'email' => 'test@example.com',
        ]);

        $testUser->assignRole('admin');
    }
}