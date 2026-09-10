<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * "Jefe de Área" NO es un rol de Spatie: es un trabajador (rol
 * "trabajador") que además encabeza una unidad orgánica de 2do nivel
 * vía `jefe_id` en `unidad_organicas`. El escalamiento de papeletas lo
 * detecta el sistema por posición en el árbol (UnidadOrganica::jefeArea()),
 * no por este seeder.
 *
 * Se le asigna como jefe de "Oficina General de Administración y
 * Finanzas", que es la unidad padre de "Oficina de Recursos Humanos"
 * (usada por JefeInmediatoUserSeeder / TrabajadorUserSeeder), para
 * poder probar el escalamiento completo Jefe Inmediato -> Jefe de Área.
 */
class JefeAreaUserSeeder extends Seeder
{
    public function run(): void
    {
        $unidad = UnidadOrganica::where('nombre', 'Oficina General de Administración y Finanzas')->firstOrFail();

        $jefeArea = User::updateOrCreate(
            ['email' => 'jefearea@mssc.test'],
            [
                'name' => 'Jorge',
                'apellido' => 'Jefe de Área',
                'dni' => '10000003',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '728',
                'unidad_organica_id' => $unidad->id,
            ]
        );

        if (! $jefeArea->hasRole('trabajador')) {
            $jefeArea->assignRole('trabajador');
        }

        $unidad->update(['jefe_id' => $jefeArea->id]);
    }
}
