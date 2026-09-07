<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paso 5, motivo Salud: sin sustento al retorno -> RETORNO_PENDIENTE_SUSTENTO,
     * 48h hábiles para justificar. Si vence sin nada -> reclasifica a Particular.
     * Si hay adjunto pendiente de revisión, el sistema no cierra solo,
     * espera confirmación humana (por eso el estado "presentado" separado de "aprobado").
     */
    public function up(): void
    {
        Schema::create('sustentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->constrained('papeletas')->cascadeOnDelete();

            $table->string('archivo_path')->nullable();
            $table->timestamp('fecha_limite');
            $table->enum('estado', ['pendiente', 'presentado', 'vencido', 'aprobado', 'observado'])
                ->default('pendiente');

            $table->timestamp('presentado_at')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sustentos');
    }
};
