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

            // fin_turno_at: instante real en que termina el turno (728, con
            // cruce de medianoche) o el horario ordinario (276) de la
            // papeleta. Se fija al crearla y los jobs de vencimiento y de
            // abandono comparan contra él, en vez de asumir que el día
            // operativo termina a medianoche.
            $table->dateTime('fin_turno_at')->nullable();

            // --- Estado (pensado para spatie/laravel-model-states) ---
            $table->string('estado')->default('pendiente_jefe');

            // --- Jefe Inmediato (Paso 2) ---
            $table->foreignId('jefe_inmediato_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_jefe_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('jefe_resuelto_at')->nullable();
            $table->unsignedTinyInteger('contador_observaciones_jefe')->default(0);

            // Observación del Jefe con respuesta escrita obligatoria:
            // - observacion_requiere_adjunto: el jefe decide, al observar,
            //   si además exige un adjunto.
            // - observacion_respuesta: el texto con el que el trabajador
            //   responde (lo ven jefe y RRHH).
            // - observacion_adjunto_path / observacion_subsanada_at: el
            //   adjunto (y cuándo) con el que el trabajador subsanó.
            // - reloj_jefe_at: instante desde el que corre el SLA del jefe.
            //   Nulo = created_at. Se fija al reabrir la papeleta en
            //   PENDIENTE_JEFE (subsanación del trabajador, reconocimiento
            //   de una observación de RRHH).
            $table->boolean('observacion_requiere_adjunto')->default(false);
            $table->string('observacion_adjunto_path')->nullable();
            $table->timestamp('observacion_subsanada_at')->nullable();
            $table->timestamp('reloj_jefe_at')->nullable();
            $table->text('observacion_respuesta')->nullable();

            // Jefe de Área: se conserva para reportes/dashboards
            // (Papeleta::scopeDeEquipoDe) y para asignar jefes inmediatos
            // adicionales. El escalamiento automático por SLA a Jefe de
            // Área ya NO existe: la decisión es siempre del Jefe Inmediato.
            $table->foreignId('jefe_area_id')->nullable()->constrained('users')->nullOnDelete();

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
            // genéricos y se reutilizan en los casos donde el automatismo
            // NO puede cerrar solo (Paso 8): sustento de Salud vencido con
            // adjunto sin revisar, y abandono no marcado (ventana de 48h
            // antes de quedar firme).
            $table->boolean('requiere_visto_bueno')->default(false);
            $table->timestamp('regularizacion_fecha_limite')->nullable();

            // --- Reclasificación (Salud sin sustento) ---
            $table->foreignId('motivo_original_id')->nullable()->constrained('motivos')->nullOnDelete();

            $table->text('justificacion')->nullable();

            // Hora en la que el trabajador declara que piensa retornar,
            // capturada al crear la papeleta. Es solo informativa/UX
            // (mostrar en el ticket cuánto falta o si ya se pasó) y no
            // bloquea ni valida nada del flujo de retorno real.
            $table->timestamp('hora_retorno_estimado')->nullable();

            // --- Adjunto inicial al crear (Sección 3, distinto del sustento de retorno) ---
            // Salud: flexible (puede o no venir aquí). Comisión: opcional.
            // NO confundir con `sustentos`, que es exclusivo del sustento posterior
            // al retorno con motivo Salud (Paso 5, ventana de 48h hábiles).
            $table->string('adjunto_inicial_path')->nullable();

            $table->timestamps();

            // ReporteHorasAcumuladasService (cierre de mes) filtra por
            // dia_operativo directamente, sin pasar por estado primero, así
            // que el índice compuesto de abajo no lo cubre.
            $table->index('dia_operativo');
            $table->index(['estado', 'dia_operativo']);

            // ProcesarVencimientoAbandono compara contra fin_turno_at.
            $table->index(['estado', 'fin_turno_at']);

            // Índice simple de trabajador_id (antes lo cubrían los unique de
            // exclusividad que ya no existen).
            $table->index('trabajador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeletas');
    }
};
