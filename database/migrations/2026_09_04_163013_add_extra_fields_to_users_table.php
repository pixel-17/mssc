<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolida en un solo archivo los campos extra de `users` que antes
 * vivían en 5 migraciones separadas (two_factor, papeletas, device
 * settings, debe_actualizar_password, activo). Va después de
 * create_sedes_table porque sede_id la referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // --- Trabajador/Jefe/RRHH son todos `users` diferenciados
            // por rol (spatie/laravel-permission). Régimen es
            // fotografiado (copiado) a la papeleta al crearla, pero aquí
            // vive el valor "actual" del trabajador.
            $table->enum('regimen', ['276', '728'])->nullable()->after('id');

            // Marca si el trabajador sigue activo (cesado, licencia
            // larga, etc. se pone en false). El generador de turnos
            // nunca crea filas en `turnos` para un inactivo.
            $table->boolean('activo')->default(true)->after('regimen');

            $table->foreignId('sede_id')->nullable()->after('regimen')
                ->constrained('sedes')->nullOnDelete();

            // Jerarquía: quién es el jefe inmediato y quién el jefe de
            // área de este trabajador. Ambos son también `users`.
            $table->foreignId('jefe_inmediato_id')->nullable()->after('sede_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('jefe_area_id')->nullable()->after('jefe_inmediato_id')
                ->constrained('users')->nullOnDelete();

            // Jetstream: two-factor authentication.
            $table->text('two_factor_secret')->after('password')->nullable();
            $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable();

            // Cuando un usuario se crea con contraseña = su DNI (ver
            // CrearUsuarioAction y UsuarioAdminForm), esta bandera
            // enciende la pantalla de actualización obligatoria (pero
            // omitible) en su primer ingreso.
            $table->boolean('debe_actualizar_password')->default(false)->after('two_factor_recovery_codes');

            $table->timestamp('two_factor_confirmed_at')->after('debe_actualizar_password')->nullable();

            // El navegador nunca deja "activar/desactivar" GPS o cámara
            // por código: solo se puede pedir el permiso (diálogo
            // nativo) y leer si quedó concedido/denegado. Lo que sí
            // guardamos aquí es la INTENCIÓN del usuario ("quiero que la
            // app use esto"), para que la UI sepa si debe ofrecer/pedir
            // el permiso. El estado real se consulta en el navegador
            // (navigator.permissions).
            $table->unsignedTinyInteger('volumen_notificacion')->default(80)->after('two_factor_confirmed_at');
            $table->boolean('permite_gps')->default(false)->after('volumen_notificacion');
            $table->boolean('permite_camara')->default(false)->after('permite_gps');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'volumen_notificacion',
                'permite_gps',
                'permite_camara',
                'two_factor_confirmed_at',
                'debe_actualizar_password',
                'two_factor_recovery_codes',
                'two_factor_secret',
            ]);
            $table->dropConstrainedForeignId('jefe_area_id');
            $table->dropConstrainedForeignId('jefe_inmediato_id');
            $table->dropConstrainedForeignId('sede_id');
            $table->dropColumn(['activo', 'regimen']);
        });
    }
};
