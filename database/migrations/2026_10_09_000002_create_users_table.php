<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trabajador, jefe, RRHH y admin son todos `users`, diferenciados por rol
     * (spatie/laravel-permission). Va después de `sedes` porque la referencia.
     *
     * `unidad_organica_id` se agrega en create_unidad_organicas_table: las
     * dos tablas se referencian entre sí (unidad.jefe_id -> users).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('apellido');
            $table->string('dni', 8)->unique(); // unicidad a nivel de BD, no solo de validación
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('profile_photo_path', 2048)->nullable();

            // Jetstream/Fortify: autenticación en dos pasos.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Valor "actual" del régimen; cada papeleta lo fotografía al crearse.
            $table->enum('regimen', ['276', '728'])->nullable();

            // false = cesado, licencia larga, etc. El generador de turnos
            // nunca crea filas para un usuario inactivo.
            $table->boolean('activo')->default(true);

            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();

            // Jerarquía derivada de la unidad orgánica (ver UserObserver).
            $table->foreignId('jefe_inmediato_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jefe_area_id')->nullable()->constrained('users')->nullOnDelete();

            // Contraseña inicial = DNI: enciende la pantalla de actualización
            // obligatoria (pero omitible) en el primer ingreso.
            $table->boolean('debe_actualizar_password')->default(false);

            // Preferencias del dispositivo: guardan la INTENCIÓN del usuario;
            // el permiso real de GPS/cámara se consulta en el navegador.
            $table->unsignedTinyInteger('volumen_notificacion')->default(80);
            $table->boolean('permite_gps')->default(false);
            $table->boolean('permite_camara')->default(false);

            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

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
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
