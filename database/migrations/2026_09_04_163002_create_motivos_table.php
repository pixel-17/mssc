<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los motivos del flujo (Particular, Salud, Comisión) tienen reglas de
     * negocio distintas (adjuntos, suma a descuento, cierre sin retorno).
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
            $table->boolean('permite_cierre_sin_retorno')->default(false); // Comisión de Servicio
            $table->boolean('requiere_sustento_en_retorno')->default(false); // Salud

            // Motivo.php documenta la regla "nunca comparar codigo ===
            // 'SALUD' para decidir lógica, siempre usar banderas" — esta
            // bandera permite que ReclasificarAParticularAction resuelva
            // el motivo destino sin hardcodear el código.
            $table->boolean('es_destino_reclasificacion')->default(false);

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos');
    }
};
