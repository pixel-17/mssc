<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los 4 motivos del flujo tienen reglas de negocio muy distintas
     * (adjuntos, bypass de aprobación, suma a descuento, cierre sin retorno).
     * En vez de hardcodear esas reglas en el código por nombre, se guardan
     * como banderas en la tabla para que el código las consulte, no las adivine.
     */
    public function up(): void
    {
        Schema::create('motivos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // PARTICULAR | SALUD | COMISION | EMERGENCIA
            $table->string('nombre');

            // Salud: 'flexible' (puede o no traer adjunto al solicitar)
            // Comisión: 'opcional'
            // Particular: 'no' (no lleva adjuntos)
            // Emergencia: 'obligatorio' (justificación obligatoria, Paso 3 de motivos)
            $table->enum('adjunto', ['no', 'opcional', 'flexible', 'obligatorio'])->default('no');

            $table->boolean('suma_descuento')->default(false); // Particular
            $table->boolean('permite_bypass_aprobacion')->default(false); // Emergencia
            $table->boolean('permite_cierre_sin_retorno')->default(false); // Comisión de Servicio
            $table->boolean('requiere_sustento_en_retorno')->default(false); // Salud
            $table->boolean('participa_regla_exclusividad')->default(true); // false solo para Emergencia (carril aparte)

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos');
    }
};
