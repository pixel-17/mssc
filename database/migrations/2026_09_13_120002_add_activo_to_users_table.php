<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca si el trabajador sigue activo (cesado, con licencia larga,
     * etc. se pone en false). El generador de turnos (manual y
     * automático) nunca crea filas en `turnos` para un inactivo — ver
     * GeneradorTurnoMensualService::generarMes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('regimen');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
