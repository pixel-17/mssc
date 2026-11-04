<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'rrhh', 'trabajador'] as $rol) {
            Role::firstOrCreate(['name' => $rol]);
        }
    }
}
