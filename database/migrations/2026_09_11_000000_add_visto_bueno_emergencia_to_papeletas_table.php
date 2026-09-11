<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 6 (Emergencia): revisión post-hoc DOBLE e independiente — Jefe y
 * RRHH revisan en paralelo, cada uno con su propio visto bueno, y
 * ninguno bloquea al otro ni al ciclo operativo del trabajador.
 *
 * A propósito son 2 columnas separadas y no una genérica reutilizando
 * revision_posthoc_estado (Paso 4): esa columna es de un solo revisor
 * (RRHH revisando lo que el jefe autorizó fuera de horario) y aquí
 * necesitamos que ambos actores queden registrados de forma
 * independiente — 'aprobado' de uno no debe pisar ni implicar el del
 * otro.
 *
 * regularizacion_fecha_limite / requiere_visto_bueno (ya existentes,
 * ver 2026_09_07_230939) NO se reutilizan para el plazo de 15 días
 * hábiles: esas dos existen para que el job de vencimiento NO cierre
 * solo mientras espera a un humano. Acá es al revés — el job de
 * vencimiento de subsanación SÍ debe actuar solo (reclasificar a
 * Particular) si nadie subsana a tiempo. Por eso el plazo vive en su
 * propia columna: subsanacion_emergencia_fecha_limite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->enum('visto_bueno_jefe_emergencia', ['pendiente', 'aprobado', 'observado'])
                ->nullable()
                ->after('justificacion');
            $table->foreignId('visto_bueno_jefe_emergencia_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('visto_bueno_jefe_emergencia_at')->nullable();

            $table->enum('visto_bueno_rrhh_emergencia', ['pendiente', 'aprobado', 'observado'])
                ->nullable()
                ->after('visto_bueno_jefe_emergencia_at');
            $table->foreignId('visto_bueno_rrhh_emergencia_por_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('visto_bueno_rrhh_emergencia_at')->nullable();

            // Plazo de subsanación (tope parametrizable, config
            // SUBSANACION_EMERGENCIA_DIAS_HABILES). Se fija en la
            // PRIMERA observación (jefe o RRHH, lo que ocurra antes) y
            // no se reinicia si el otro observa después.
            $table->timestamp('subsanacion_emergencia_fecha_limite')->nullable();

            // Adjunto que el trabajador sube al subsanar. Separado de
            // adjunto_inicial_path (ese es el de la creación de la
            // papeleta) y de sustentos.archivo_path (exclusivo de Salud).
            $table->string('subsanacion_emergencia_adjunto_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visto_bueno_jefe_emergencia_por_id');
            $table->dropConstrainedForeignId('visto_bueno_rrhh_emergencia_por_id');

            $table->dropColumn([
                'visto_bueno_jefe_emergencia',
                'visto_bueno_jefe_emergencia_at',
                'visto_bueno_rrhh_emergencia',
                'visto_bueno_rrhh_emergencia_at',
                'subsanacion_emergencia_fecha_limite',
                'subsanacion_emergencia_adjunto_path',
            ]);
        });
    }
};
