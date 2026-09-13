<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un registro por (trabajador, año, mes) que ya tiene turnos
     * generados. `origen` distingue si lo cargó una persona (Admin o
     * Jefe, vía ConfiguracionTurnoForm) o si lo generó solo el comando
     * `turnos:generar-proximo-mes` por no haber actualización nueva.
     *
     * El unique(user_id, anio, mes) es la guardia real de "no pisar
     * lo que ya se cargó": el comando automático solo genera el mes
     * si todavía no existe fila para ese (trabajador, año, mes),
     * manual o automática.
     */
    public function up(): void
    {
        Schema::create('cargas_turno_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->enum('origen', ['manual', 'automatico']);
            $table->foreignId('configuracion_turno_id')->nullable()->constrained('configuraciones_turno')->nullOnDelete();
            $table->foreignId('generado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_turno_mensuales');
    }
};
