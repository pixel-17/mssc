<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuando al mover a un trabajador se decide quitarle sus jefes
 * inmediatos adicionales, el movimiento guarda aquí a cuáles (lista de
 * {jefe_id, asignado_por_id}) para poder devolvérselos si se deshace.
 * null = no se quitó ninguno.
 *
 * (La sede anterior/nueva ya están en sede_anterior_id / sede_nueva_id.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_organigrama', function (Blueprint $table) {
            $table->json('jefes_adicionales_quitados')->nullable()->after('jefe_area_nuevo_id');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_organigrama', function (Blueprint $table) {
            $table->dropColumn('jefes_adicionales_quitados');
        });
    }
};
