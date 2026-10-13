<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
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

        // Un 728 sin turno vigente no está disponible para decidir (ver
        // DecisorDisponibleService::estaDeDescanso). Mismo criterio que el
        // jefe inmediato: turno según la hora de la siembra.
        $ahora = now();
        $hora = (int) $ahora->format('G');
        $turno = match (true) {
            $hora >= 6 && $hora < 14 => 'MANANA',
            $hora >= 14 && $hora < 22 => 'TARDE',
            default => 'NOCHE',
        };
        $fechaAncla = $hora < 6 ? $ahora->copy()->subDay() : $ahora->copy();
        $actor = User::where('email', 'admin@mssc.test')->firstOrFail();

        app(GeneradorTurnoMensualService::class)->cargarConfiguracion(
            trabajador: $jefeArea,
            turno: $turno,
            fechaAncla: $fechaAncla,
            actor: $actor,
        );
    }
}
