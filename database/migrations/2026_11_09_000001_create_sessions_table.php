<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SESSION_DRIVER=database es el valor por defecto (.env.example y
     * config/session.php), pero a este proyecto le faltaba la migración
     * de la tabla 'sessions'. Sin ella, el primer request que toque
     * sesión en producción revienta con "Base table or view not found:
     * sessions" — no es un caso límite, es cualquier ruta del grupo
     * 'web'. Esquema idéntico al que genera `php artisan session:table`.
     *
     * hasTable(): en algunos entornos (este incluido) la tabla ya
     * existe físicamente porque se creó manualmente alguna vez sin
     * dejar la migración en el repo. El guard evita que esos entornos
     * truenen con "table already exists" sin dejar de crearla en un
     * entorno nuevo (producción) donde de verdad falta.
     */
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};