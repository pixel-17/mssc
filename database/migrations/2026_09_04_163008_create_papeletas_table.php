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

            // Paso 5: FINALIZADO_SIN_RETORNO tiene dos causas distintas y hay
            // que poder filtrarlas en reportes sin parsear texto libre:
            // COMISION_SERVICIO_CAMPO (visto bueno humano, motivo
            // permite_cierre_sin_retorno) y ABANDONO_NO_MARCADO (job de
            // vencimiento, turno terminó sin marcación).
            $table->enum('causa_finalizacion_sin_retorno', ['comision_servicio_campo', 'abandono_no_marcado'])
                ->nullable();

            // requiere_visto_bueno / regularizacion_fecha_limite son
            // genéricos y se reutilizan en los 3 casos donde el
            // automatismo NO puede cerrar solo (Paso 8): sustento de
            // Salud vencido con adjunto sin revisar, abandono no marcado
            // (ventana de 48h antes de quedar firme) y subsanación de
            // Emergencia observada (ventana de 15 días hábiles).
            $table->boolean('requiere_visto_bueno')->default(false);
            $table->timestamp('regularizacion_fecha_limite')->nullable();

            // --- Reclasificación (Salud sin sustento / Emergencia observada) ---
            $table->foreignId('motivo_original_id')->nullable()->constrained('motivos')->nullOnDelete();

            $table->text('justificacion')->nullable(); // obligatoria en Emergencia

            // Hora en la que el trabajador declara que piensa retornar,
            // capturada al crear la papeleta. Es solo informativa/UX
            // (mostrar en el ticket cuánto falta o si ya se pasó) y no
            // bloquea ni valida nada del flujo de retorno real.
            $table->timestamp('hora_retorno_estimado')->nullable();

            // Paso 6 (Emergencia): revisión post-hoc DOBLE e independiente —
            // Jefe y RRHH revisan en paralelo, cada uno con su propio visto
            // bueno, sin bloquearse entre sí ni al ciclo operativo del
            // trabajador. A propósito son columnas separadas y no
            // reutilizan revision_posthoc_estado (esa es de un solo
            // revisor: RRHH revisando lo que el jefe autorizó fuera de
            // horario).
            $table->enum('visto_bueno_jefe_emergencia', ['pendiente', 'aprobado', 'observado'])->nullable();
            $table->foreignId('visto_bueno_jefe_emergencia_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('visto_bueno_jefe_emergencia_at')->nullable();

            $table->enum('visto_bueno_rrhh_emergencia', ['pendiente', 'aprobado', 'observado'])->nullable();
            $table->foreignId('visto_bueno_rrhh_emergencia_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('visto_bueno_rrhh_emergencia_at')->nullable();

            // Plazo de subsanación (tope parametrizable, config
            // SUBSANACION_EMERGENCIA_DIAS_HABILES). Se fija en la PRIMERA
            // observación (jefe o RRHH, lo que ocurra antes) y no se
            // reinicia si el otro observa después. NO reutiliza
            // regularizacion_fecha_limite: esa existe para que el job de
            // vencimiento NO cierre solo mientras espera a un humano; acá
            // es al revés, el job SÍ debe actuar solo (reclasificar a
            // Particular) si nadie subsana a tiempo.
            $table->timestamp('subsanacion_emergencia_fecha_limite')->nullable();

            // Adjunto que el trabajador sube al subsanar. Separado de
            // adjunto_inicial_path (el de la creación) y de
            // sustentos.archivo_path (exclusivo de Salud).
            $table->string('subsanacion_emergencia_adjunto_path')->nullable();

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
