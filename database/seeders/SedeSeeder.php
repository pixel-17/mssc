<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Seeder;

/**
 * Sede por defecto: Municipalidad Distrital de Santiago (Cusco). Es la sede
 * de todos los usuarios que no tengan una asignada (ver OrganigramaPruebaSeeder).
 *
 * Las coordenadas son APROXIMADAS: la validación GPS del retorno usa radio de
 * 150 m. Verifícalas en Google Maps y corrígelas desde Sedes si hace falta.
 */
class SedeSeeder extends Seeder
{
    public function run(): void
    {
        Sede::updateOrCreate(
            ['nombre' => 'Municipalidad Distrital de Santiago (Cusco)'],
            [
                'direccion' => 'Distrito de Santiago, Cusco, Perú',
                'latitud' => -13.5361000,
                'longitud' => -71.9629000,
                'radio_metros' => 150,
                'activo' => true,
            ]
        );
    }
}