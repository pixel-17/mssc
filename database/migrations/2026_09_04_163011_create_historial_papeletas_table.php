<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paso 8: cada transición registra quién, cuándo, estado anterior/nuevo,
     * motivo anterior/nuevo si cambió, y justificación. Append-only:
     * a propósito NO tiene updated_at, y a nivel de aplicación nunca se
     * debe hacer UPDATE ni DELETE sobre esta tabla (revocar el permiso de
     * update/delete al usuario de BD de la app es la forma más segura de
     * garantizarlo, además de la disciplina en el código).
     */
    public function up(): void
    {
        Schema::create('historial_papeletas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->constrained('papeletas')->cascadeOnDelete();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('actor_tipo', ['trabajador', 'jefe_inmediato', 'jefe_area', 'rrhh', 'sistema']);

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('motivo_anterior_id')->nullable()->constrained('motivos')->nullOnDelete();
            $table->foreignId('motivo_nuevo_id')->nullable()->constrained('motivos')->nullOnDelete();

            $table->text('justificacion')->nullable();
            $table->json('metadata')->nullable(); // ej. resultado de validación GPS, SLA usado, etc.

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_papeletas');
    }
};
