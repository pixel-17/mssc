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

            // --- Fotografía inmutable al crear ---
            // Aunque después cambien la sede o el régimen del trabajador,
            // la papeleta conserva el valor que tenía al crearse.
            $table->foreignId('sede_id')->constrained('sedes');
            $table->enum('regimen', ['276', '728']);
            $table->date('dia_operativo');

            // Instante real en que termina el turno (728, con cruce de
            // medianoche) o el horario ordinario (276). Los jobs de vencimiento
            // y de abandono comparan contra él.
            $table->dateTime('fin_turno_at')->nullable();

            // Nombre estable del estado (spatie/laravel-model-states): ver
            // `$name` en cada clase de App\States\Papeleta.
            //   pendiente_jefe, observada_por_jefe, pendiente_rrhh, observada_por_rrhh,
            //   autorizada_y_corriendo, en_justificacion, cerrada, finalizada,
            //   rechazada, vencida, cancelada
            $table->string('estado')->default('pendiente_jefe');

            // --- Jefe Inmediato ---
            $table->foreignId('jefe_inmediato_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_jefe_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('jefe_resuelto_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_jefe')->default(0);

            // Observación del jefe con respuesta escrita obligatoria del trabajador.
            $table->boolean('observacion_requiere_adjunto')->default(false);
            $table->string('observacion_adjunto_path')->nullable();
            $table->timestamp('observacion_subsanada_at')->nullable();
            $table->text('observacion_respuesta')->nullable();

            // Instante desde el que corre el SLA del jefe. Nulo = created_at.
            // Se fija al reabrir en PENDIENTE_JEFE (subsanación, o
            // reconocimiento de una observación de RRHH).
            $table->timestamp('reloj_jefe_at')->nullable();

            // Jefe de Área: para reportes/dashboards (Papeleta::scopeDeEquipoDe).
            // La decisión es siempre del Jefe Inmediato.
            $table->foreignId('jefe_area_id')->nullable()->constrained('users')->nullOnDelete();

            // --- RRHH ---
            $table->foreignId('resuelto_por_rrhh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rrhh_resuelto_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_rrhh')->default(0);
            $table->boolean('autorizado_con_rrhh_fuera_horario')->default(false);

            // --- Revisión post-hoc ---
            //   pendiente ──(RRHH aprueba)──► aprobada
            //   pendiente ──(RRHH observa)──► observada ──(jefe responde)──► respondida
            //   respondida ─(RRHH aprueba)──► aprobada | (RRHH observa)──► observada …
            //   al alcanzar el tope (o sin jefe que autorizara) ──► observada_firme
            $table->enum('revision_posthoc_estado', [
                'no_aplica',
                'pendiente',
                'observada',
                'respondida',
                'aprobada',
                'observada_firme',
            ])->default('no_aplica');
            $table->foreignId('revision_posthoc_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revision_posthoc_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_posthoc')->default(0);
            $table->text('posthoc_observacion')->nullable();
            $table->text('posthoc_respuesta')->nullable();
            $table->string('posthoc_adjunto_path')->nullable();
            $table->timestamp('posthoc_respondida_at')->nullable();

            // --- Salida real (inmutable una vez fijada) ---
            $table->timestamp('hora_salida_real')->nullable();

            // --- Refrigerio (solo 276) ---
            $table->unsignedSmallInteger('descuento_refrigerio_minutos')->default(0);

            // --- Rechazo / cancelación / vencimiento ---
            $table->foreignId('rechazada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_rechazo')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamp('vencida_at')->nullable();

            // Causa de un cierre sin retorno, filtrable en reportes sin parsear
            // texto: comision_servicio_campo | abandono_no_marcado | salud_no_justificada.
            $table->string('causa_finalizacion_sin_retorno', 40)->nullable();

            // Casos donde el automatismo no cierra solo (ver RrhhIndex).
            $table->boolean('requiere_visto_bueno')->default(false);
            $table->timestamp('regularizacion_fecha_limite')->nullable();

            // --- Reclasificación (Salud sin sustento -> Particular) ---
            $table->foreignId('motivo_original_id')->nullable()->constrained('motivos')->nullOnDelete();

            $table->text('justificacion')->nullable();

            // Hora en que el trabajador dice que retornará: solo informativa/UX.
            $table->timestamp('hora_retorno_estimado')->nullable();

            // Adjunto inicial al crear (Salud: flexible; Comisión: opcional).
            // Distinto de `sustentos`, que es el sustento posterior al retorno.
            $table->string('adjunto_inicial_path')->nullable();

            $table->timestamps();

            $table->index('trabajador_id');
            $table->index('dia_operativo');            // cierre de mes (ReporteHorasAcumuladasService)
            $table->index(['estado', 'dia_operativo']);
            $table->index(['estado', 'fin_turno_at']); // ProcesarAbandonoNoMarcado
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeletas');
    }
};
