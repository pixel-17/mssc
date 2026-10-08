<?php

namespace Database\Seeders;

use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Asigna un jefe de prueba a cada unidad del organigrama que todavía no lo
 * tiene (jefe_id null). Cada unidad tiene una sola jefatura: si la unidad
 * tiene hijos, ese jefe es jefe de área de ellos; si no, es jefe inmediato de
 * sus miembros. El sistema deduce cuál es cuál por la posición en el árbol.
 *
 * Los nombres son PLACEHOLDER ("Jefe" + nombre de la unidad). Reemplázalos
 * desde Usuarios. Contraseña: "password". Régimen 276 para no depender de
 * turnos 728 configurados.
 *
 * Debe correr DESPUÉS de UnidadOrganicaSeeder, SedeSeeder y de los
 * seeders de usuarios de prueba (JefeArea / JefeInmediato), para no
 * sobrescribir los jefes que ya asignan.
 */
class OrganigramaPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $rolTrabajador = Role::where('name', 'trabajador')->firstOrFail();
        $sede = Sede::where('activo', true)->orderBy('id')->first();

        $unidades = UnidadOrganica::whereNotNull('parent_id')
            ->whereNull('jefe_id')
            ->orderBy('id')
            ->get();

        // DNI de prueba único por unidad, en el rango 20000001+ (no choca con los seeders existentes).
        $contador = 20000000;

        foreach ($unidades as $unidad) {
            $contador++;

            $jefe = User::create([
                'name' => 'Jefe',
                'apellido' => mb_substr($unidad->nombre, 0, 250),
                'dni' => (string) $contador,
                'email' => "jefe{$contador}@mssc.test",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'regimen' => '276',
                'sede_id' => $sede?->id,
                'unidad_organica_id' => $unidad->id,
                'activo' => true,
            ]);

            $jefe->assignRole($rolTrabajador);

            $unidad->update(['jefe_id' => $jefe->id]);
        }

        // Usuarios que ya existían (de otros seeders de prueba) sin sede: sede por defecto.
        if ($sede) {
            User::whereNull('sede_id')
                ->whereNotNull('unidad_organica_id')
                ->update(['sede_id' => $sede->id]);
        }

        // Recalcula jefe inmediato y jefe de área de TODOS los usuarios según el
        // organigrama ya armado (los creados antes de asignar jefes quedaban en null).
        Artisan::call('jefaturas:recalcular');
    }
}