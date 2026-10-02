<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Borrar una unidad con sub-unidades o con personas dejaba huérfanos en
     * silencio (nullOnDelete): raíces sin jefe de área, personas sin unidad.
     * Con restrictOnDelete la BD respalda la regla que EliminarUnidadOrganicaAction
     * ya valida en la aplicación.
     *
     * Antes de aplicarla, comprobar que no existan huérfanos que dependan del
     * comportamiento anterior (la migración no toca datos).
     */
    public function up(): void
    {
        Schema::table('unidad_organicas', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('unidad_organicas')->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unidad_organica_id']);
            $table->foreign('unidad_organica_id')->references('id')->on('unidad_organicas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unidad_organica_id']);
            $table->foreign('unidad_organica_id')->references('id')->on('unidad_organicas')->nullOnDelete();
        });

        Schema::table('unidad_organicas', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('unidad_organicas')->nullOnDelete();
        });
    }
};
