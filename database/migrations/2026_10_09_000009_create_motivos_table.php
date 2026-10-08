<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las reglas de cada motivo (adjuntos, descuento, justificación) viven
     * como banderas en la tabla: el código las consulta, nunca compara `codigo`.
     */
    public function up(): void
    {
        Schema::create('motivos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // PARTICULAR | SALUD | COMISION | EMERGENCIA
            $table->string('nombre');

            // Salud: 'flexible' · Comisión: 'opcional' · Particular: 'no' · Emergencia: 'obligatorio'
            $table->enum('adjunto', ['no', 'opcional', 'flexible', 'obligatorio'])->default('no');

            $table->boolean('suma_descuento')->default(false);
            $table->boolean('requiere_sustento_en_retorno')->default(false); // Salud

            // Plazo propio de justificación; null = configuración global SUSTENTO_HORAS_HABILES.
            $table->unsignedSmallInteger('plazo_justificacion_horas_habiles')->nullable();

            // Motivo al que se reclasifica una Salud sin sustento
            // (ReclasificarAParticularAction lo resuelve por esta bandera).
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
