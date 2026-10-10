<?php

namespace Database\Seeders;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
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

        $this->cargarTurno($jefeInmediato);
    }

    /**
     * El jefe inmediato es 728: sin turno cargado, CrearPapeletaAction rechaza
     * las papeletas de su equipo ("Tu jefe inmediato no tiene un turno vigente").
     * Se le asigna el turno que corresponde a la hora en que corre el seed
     * (MANANA 06-14, TARDE 14-22, NOCHE el resto), con fecha ancla de hoy
     * (o ayer de 00:00 a 06:00, para que hoy caiga en día de trabajo).
     */
    private function cargarTurno(User $jefeInmediato): void
    {
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
            trabajador: $jefeInmediato,
            turno: $turno,
            fechaAncla: $fechaAncla,
            actor: $actor,
        );
    }
}