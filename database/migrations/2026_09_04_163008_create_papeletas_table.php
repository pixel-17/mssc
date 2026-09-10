<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papeletas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabajador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('motivo_id')->constrained('motivos');

            // --- Fotografía inmutable al crear (Paso 1) ---
            // Aunque después cambien la sede o el régimen del trabajador,
            // esta papeleta conserva el valor que tenía al momento de crearse.
            $table->foreignId('sede_id')->constrained('sedes');
            $table->enum('regimen', ['276', '728']);
            $table->date('dia_operativo');

            // --- Estado (pensado para spatie/laravel-model-states) ---
            $table->string('estado')->default('pendiente_jefe');
            $table->boolean('es_emergencia')->default(false);

            // --- Regla de exclusividad a nivel de BD (Sección 3) ---
            // Solo puede tener valor 1 mientras la papeleta esté "activa";
            // se pone en NULL al llegar a un estado terminal. Como MySQL
            // no restringe múltiples NULL en un índice único, esto permite
            // "máximo 1 activa" sin bloquear el historial.
            $table->boolean('slot_normal_activo')->nullable();
            $table->boolean('slot_emergencia_activo')->nullable();

            // --- Jefe Inmediato (Paso 2) ---
            $table->foreignId('jefe_inmediato_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_jefe_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('jefe_resuelto_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_jefe')->default(0);

            // --- Escalamiento a Jefe de Área ---
            $table->foreignId('jefe_area_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalado_jefe_area_at')->nullable();

            // --- RRHH (Paso 3) ---
            $table->foreignId('resuelto_por_rrhh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rrhh_resuelto_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_rrhh')->default(0);
            $table->boolean('autorizado_con_rrhh_fuera_horario')->default(false);

            // --- Revisión post-hoc (Paso 4) ---
            $table->enum('revision_posthoc_estado', ['no_aplica', 'pendiente', 'aprobada', 'observada'])
                ->default('no_aplica');
            $table->foreignId('revision_posthoc_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revision_posthoc_at')->nullable();

            // --- Salida real (inmutable una vez fijada) ---
            $table->timestamp('hora_salida_real')->nullable();

            // --- Refrigerio (Paso 7, solo 276) ---
            $table->unsignedSmallInteger('descuento_refrigerio_minutos')->default(0);

            // --- Rechazo / cancelación / vencimiento ---
            $table->foreignId('rechazada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_rechazo')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamp('vencida_at')->nullable();

            // --- Reclasificación (Salud sin sustento / Emergencia observada) ---
            $table->foreignId('motivo_original_id')->nullable()->constrained('motivos')->nullOnDelete();

            $table->text('justificacion')->nullable(); // obligatoria en Emergencia

            // --- Adjunto inicial al crear (Sección 3, distinto del sustento de retorno) ---
            // Salud: flexible (puede o no venir aquí). Comisión: opcional.
            // NO confundir con `sustentos`, que es exclusivo del sustento posterior
            // al retorno con motivo Salud (Paso 5, ventana de 48h hábiles).
            $table->string('adjunto_inicial_path')->nullable();

            $table->timestamps();

            $table->unique(['trabajador_id', 'slot_normal_activo']);
            $table->unique(['trabajador_id', 'slot_emergencia_activo']);
            $table->index(['estado', 'dia_operativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeletas');
    }
};
