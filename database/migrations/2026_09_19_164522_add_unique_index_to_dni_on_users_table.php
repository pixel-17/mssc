<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antes de esto, la unicidad del DNI solo la garantizaba la validación
 * de CrearUsuarioRequest (unique:users,dni) — a nivel de motor de base
 * de datos no había ningún constraint. Dos requests casi simultáneos
 * podían colarse ambos y dejar DNIs duplicados. Este índice lo cierra
 * en la capa que realmente lo puede garantizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('dni');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['dni']);
        });
    }
};
