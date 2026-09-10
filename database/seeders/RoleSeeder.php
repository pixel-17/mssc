<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Los 3 roles reales del sistema. "jefe" NO es un rol de Spatie: un
 * jefe es un trabajador cualquiera al que otros usuarios apuntan vía
 * jefe_inmediato_id/jefe_area_id (ver PapeletaPolicy). admin nunca
 * decide papeletas, solo administra catálogos vía Filament.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'rrhh', 'trabajador'] as $rol) {
            Role::firstOrCreate(['name' => $rol]);
        }
    }
}
