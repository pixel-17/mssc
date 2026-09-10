<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * "Jefe Inmediato" tampoco es un rol de Spatie: es un trabajador (rol
 * "trabajador") que encabeza la unidad orgánica directa del trabajador
 * de prueba, en este caso "Oficina de Recursos Humanos" (hija de
 * "Oficina General de Administración y Finanzas", encabezada por el
 * Jefe de Área de JefeAreaUserSeeder).
 *
 * Debe correr después de JefeAreaUserSeeder para que exista el árbol
 * completo antes de que TrabajadorUserSeeder arme el escalamiento.
 */
class JefeInmediatoUserSeeder extends Seeder
{
    public function run(): void
    {
        $unidad = UnidadOrganica::where('nombre', 'Oficina de Recursos Humanos')->firstOrFail();

        $jefeInmediato = User::updateOrCreate(
            ['email' => 'jefeinmediato@mssc.test'],
            [
                'name' => 'Iván',
                'apellido' => 'Jefe Inmediato',
                'dni' => '10000004',
                'password' => 'password',
                'email_verified_at' => now(),
                'regimen' => '728',
                'unidad_organica_id' => $unidad->id,
            ]
        );

        if (! $jefeInmediato->hasRole('trabajador')) {
            $jefeInmediato->assignRole('trabajador');
        }

        $unidad->update(['jefe_id' => $jefeInmediato->id]);
    }
}
