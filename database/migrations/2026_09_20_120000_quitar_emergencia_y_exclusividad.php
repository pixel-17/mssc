<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira del sistema el motivo Emergencia y todo lo que existía solo
 * para él, más la regla de "una papeleta activa por trabajador":
 *
 * - papeletas: unique(trabajador_id, slot_*), columnas slot_*,
 *   es_emergencia, visto_bueno_*_emergencia*, subsanacion_emergencia_*.
 * - motivos: permite_bypass_aprobacion, participa_regla_exclusividad.
 * - configuraciones: SUBSANACION_EMERGENCIA_DIAS_HABILES.
 * - motivos: la fila EMERGENCIA se borra si nadie la usa; si ya hay
 *   papeletas con ese motivo se DESACTIVA (no se borra) para que el
 *   historial siga mostrando su nombre.
 *
 * NO se tocan: revision_posthoc_* (RRHH fuera de horario),
 * requiere_visto_bueno / regularizacion_fecha_limite (abandono y
 * sustento de Salud), motivo_original_id ni es_destino_reclasificacion
 * (reclasificación de Salud a Particular), ni el estado
 * ReclasificadoAParticular.
 *
 * Cada paso comprueba si ya se aplicó, así que se puede volver a
 * ejecutar tras un fallo a medias (MySQL no revierte DDL).
 */
return new class extends Migration
{
    private const UNICOS = [
        'papeletas_trabajador_id_slot_normal_activo_unique' => ['trabajador_id', 'slot_normal_activo'],
        'papeletas_trabajador_id_slot_emergencia_activo_unique' => ['trabajador_id', 'slot_emergencia_activo'],
    ];

    /** Columnas con llave foránea a users: se sueltan con dropConstrainedForeignId. */
    private const COLUMNAS_FK = [
        'visto_bueno_jefe_emergencia_por_id',
        'visto_bueno_rrhh_emergencia_por_id',
    ];

    private const COLUMNAS_PAPELETAS = [
        'slot_normal_activo',
        'slot_emergencia_activo',
        'es_emergencia',
        'visto_bueno_jefe_emergencia',
        'visto_bueno_jefe_emergencia_at',
        'visto_bueno_rrhh_emergencia',
        'visto_bueno_rrhh_emergencia_at',
        'subsanacion_emergencia_fecha_limite',
        'subsanacion_emergencia_adjunto_path',
    ];

    private const COLUMNAS_MOTIVOS = [
        'permite_bypass_aprobacion',
        'participa_regla_exclusividad',
    ];

    public function up(): void
    {
        $this->retirarMotivoEmergencia();

        DB::table('configuraciones')->where('clave', 'SUBSANACION_EMERGENCIA_DIAS_HABILES')->delete();

        // 1) Índices únicos de exclusividad. Antes se crea un índice simple
        //    sobre trabajador_id: en MySQL la llave foránea de trabajador_id
        //    se apoyaba en estos únicos y no dejaría soltarlos sin otro índice.
        if (! Schema::hasIndex('papeletas', 'papeletas_trabajador_id_index')) {
            Schema::table('papeletas', function (Blueprint $table) {
                $table->index('trabajador_id');
            });
        }

        foreach (self::UNICOS as $nombre => $columnas) {
            if (Schema::hasIndex('papeletas', $nombre)) {
                Schema::table('papeletas', function (Blueprint $table) use ($columnas) {
                    $table->dropUnique($columnas);
                });
            }
        }

        // 2) Columnas de papeletas (una llamada por columna con FK; el resto junto).
        foreach (self::COLUMNAS_FK as $columna) {
            if (Schema::hasColumn('papeletas', $columna)) {
                Schema::table('papeletas', function (Blueprint $table) use ($columna) {
                    $table->dropConstrainedForeignId($columna);
                });
            }
        }

        $existentes = array_values(array_filter(
            self::COLUMNAS_PAPELETAS,
            fn (string $c) => Schema::hasColumn('papeletas', $c),
        ));

        if ($existentes) {
            Schema::table('papeletas', function (Blueprint $table) use ($existentes) {
                $table->dropColumn($existentes);
            });
        }

        // 3) Banderas de motivos.
        $existentes = array_values(array_filter(
            self::COLUMNAS_MOTIVOS,
            fn (string $c) => Schema::hasColumn('motivos', $c),
        ));

        if ($existentes) {
            Schema::table('motivos', function (Blueprint $table) use ($existentes) {
                $table->dropColumn($existentes);
            });
        }
    }

    /**
     * Restaura la ESTRUCTURA (columnas e índices únicos), no los datos:
     * la fila del motivo Emergencia y los valores borrados no vuelven.
     */
    public function down(): void
    {
        Schema::table('motivos', function (Blueprint $table) {
            $table->boolean('permite_bypass_aprobacion')->default(false);
            $table->boolean('participa_regla_exclusividad')->default(true);
        });

        Schema::table('papeletas', function (Blueprint $table) {
            $table->boolean('es_emergencia')->default(false);
            $table->boolean('slot_normal_activo')->nullable();
            $table->boolean('slot_emergencia_activo')->nullable();

            $table->enum('visto_bueno_jefe_emergencia', ['pendiente', 'aprobado', 'observado'])->nullable();
            $table->foreignId('visto_bueno_jefe_emergencia_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('visto_bueno_jefe_emergencia_at')->nullable();

            $table->enum('visto_bueno_rrhh_emergencia', ['pendiente', 'aprobado', 'observado'])->nullable();
            $table->foreignId('visto_bueno_rrhh_emergencia_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('visto_bueno_rrhh_emergencia_at')->nullable();

            $table->timestamp('subsanacion_emergencia_fecha_limite')->nullable();
            $table->string('subsanacion_emergencia_adjunto_path')->nullable();
        });

        Schema::table('papeletas', function (Blueprint $table) {
            $table->unique(['trabajador_id', 'slot_normal_activo']);
            $table->unique(['trabajador_id', 'slot_emergencia_activo']);
        });

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'SUBSANACION_EMERGENCIA_DIAS_HABILES'],
            [
                'valor' => '15',
                'descripcion' => 'Días hábiles para subsanar una Emergencia observada antes de reclasificar a Particular.',
            ],
        );
    }

    private function retirarMotivoEmergencia(): void
    {
        $motivo = DB::table('motivos')->where('codigo', 'EMERGENCIA')->first();

        if (! $motivo) {
            return;
        }

        $enUso = DB::table('papeletas')->where('motivo_id', $motivo->id)->exists()
            || DB::table('papeletas')->where('motivo_original_id', $motivo->id)->exists()
            || DB::table('historial_papeletas')->where('motivo_anterior_id', $motivo->id)->exists()
            || DB::table('historial_papeletas')->where('motivo_nuevo_id', $motivo->id)->exists();

        if ($enUso) {
            DB::table('motivos')->where('id', $motivo->id)->update(['activo' => false]);

            return;
        }

        DB::table('motivos')->where('id', $motivo->id)->delete();
    }
};
