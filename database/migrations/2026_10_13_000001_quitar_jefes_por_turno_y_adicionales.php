<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Cada trabajador tiene UN solo jefe inmediato (users.jefe_inmediato_id, por su
 * unidad) y cada unidad UN solo jefe (unidades_organicas.jefe_id), sin importar
 * el régimen ni el turno. Se eliminan las tablas de jefes por turno, de jefes
 * adicionales y de candidatos a jefe de cada papeleta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('papeleta_jefes_candidatos');
        Schema::dropIfExists('jefes_inmediatos_adicionales');
        Schema::dropIfExists('jefes_turno');
    }

    public function down(): void
    {
        // Sin reversa: estas asignaciones ya no existen en el modelo de negocio.
    }
};
