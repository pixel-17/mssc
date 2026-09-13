<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuando un usuario se crea con contraseña = su DNI (ver
 * CrearUsuarioAction y UsuarioAdminForm), esta bandera se enciende
 * para que RedirigirSiDebeActualizarPassword le muestre, solo en su
 * primer ingreso, la pantalla para actualizarla — que puede omitir
 * (es opcional). Se apaga sola en cuanto actualiza o la omite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('debe_actualizar_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('debe_actualizar_password');
        });
    }
};
