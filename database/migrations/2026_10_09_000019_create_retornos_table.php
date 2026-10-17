<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paso 5: evidencia normal es foto + GPS + hora del servidor simultáneos.
     * Única excepción: falla de conectividad -> jefe marca manual, sin
     * foto/GPS, con justificación obligatoria y alerta a RRHH.
     */
    public function up(): void
    {
        Schema::create('retornos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->unique()->constrained('papeletas')->cascadeOnDelete();

            $table->string('foto_path')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->boolean('dentro_de_radio')->nullable(); // bandera de observación, no bloquea

            $table->timestamp('hora_servidor')->nullable();

            $table->boolean('marcado_manual')->default(false);

            // Cuando ProcesarAbandonoNoMarcado cierra una papeleta sin que el
            // trabajador marcara su retorno, igual necesitamos UNA hora de fin
            // para poder calcular cuánto tiempo estuvo afuera (hora_servidor
            // = fin_turno_at en ese caso, no una marcación real). Este flag
            // distingue ambos casos para que la UI no diga "retorno
            // registrado" cuando en realidad es un abandono sin evidencia.
            $table->boolean('generado_por_abandono')->default(false);

            $table->foreignId('marcado_manual_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justificacion_manual')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retornos');
    }
};
