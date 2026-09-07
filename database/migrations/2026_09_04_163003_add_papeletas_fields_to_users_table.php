<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajusta esto si en el proyecto real "trabajador" es una tabla aparte
     * en vez de extender `users`. Aquí se asume que Trabajador/Jefe/RRHH
     * son todos `users` diferenciados por rol (spatie/laravel-permission),
     * como indica el modelo actual del proyecto.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Régimen es fotografiado (copiado) a la papeleta al crearla,
            // pero aquí vive el valor "actual" del trabajador.
            $table->enum('regimen', ['CAS', '728'])->nullable()->after('id');

            $table->foreignId('sede_id')->nullable()->after('regimen')
                ->constrained('sedes')->nullOnDelete();

            // Jerarquía: quién es el jefe inmediato y quién el jefe de área
            // de este trabajador. Ambos son también `users`.
            $table->foreignId('jefe_inmediato_id')->nullable()->after('sede_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('jefe_area_id')->nullable()->after('jefe_inmediato_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jefe_area_id');
            $table->dropConstrainedForeignId('jefe_inmediato_id');
            $table->dropConstrainedForeignId('sede_id');
            $table->dropColumn('regimen');
        });
    }
};
