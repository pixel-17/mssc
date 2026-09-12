<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El navegador nunca deja "activar/desactivar" GPS o cámara por
     * código: solo se puede pedir el permiso (diálogo nativo) y leer
     * si quedó concedido/denegado. Lo que sí guardamos aquí es la
     * INTENCIÓN del usuario ("quiero que la app use esto"), para que
     * la UI sepa si debe ofrecer/pedir el permiso o no. El estado real
     * del permiso se consulta en el navegador (navigator.permissions).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('volumen_notificacion')->default(80)->after('two_factor_confirmed_at');
            $table->boolean('permite_gps')->default(false)->after('volumen_notificacion');
            $table->boolean('permite_camara')->default(false)->after('permite_gps');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['volumen_notificacion', 'permite_gps', 'permite_camara']);
        });
    }
};
