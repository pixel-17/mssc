<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 5: FINALIZADO_SIN_RETORNO tiene dos causas distintas y hay que
 * poder filtrarlas en reportes sin parsear el texto libre del
 * historial: COMISION_SERVICIO_CAMPO (visto bueno humano, motivo
 * permite_cierre_sin_retorno) y ABANDONO_NO_MARCADO (job de
 * vencimiento, turno terminó sin marcación).
 *
 * requiere_visto_bueno / regularizacion_fecha_limite son genéricos y
 * se reutilizan en los 3 casos donde el automatismo NO puede cerrar
 * solo (Paso 8): sustento de Salud vencido con adjunto sin revisar,
 * abandono no marcado (ventana de 48h antes de quedar firme) y
 * subsanación de Emergencia observada (ventana de 15 días hábiles).
 * Una sola columna de bandera + una de plazo evita repetir el mismo
 * par en cada caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->enum('causa_finalizacion_sin_retorno', ['comision_servicio_campo', 'abandono_no_marcado'])
                ->nullable()
                ->after('vencida_at');

            $table->boolean('requiere_visto_bueno')->default(false)->after('causa_finalizacion_sin_retorno');
            $table->timestamp('regularizacion_fecha_limite')->nullable()->after('requiere_visto_bueno');
        });

        // Motivo.php ya documenta la regla "nunca comparar codigo === 'SALUD'
        // para decidir lógica, siempre usar banderas" — esta bandera es la
        // pieza que faltaba para que ReclasificarAParticularAction pueda
        // resolver el motivo destino sin hardcodear el código.
        Schema::table('motivos', function (Blueprint $table) {
            $table->boolean('es_destino_reclasificacion')->default(false)->after('participa_regla_exclusividad');
        });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropColumn(['causa_finalizacion_sin_retorno', 'requiere_visto_bueno', 'regularizacion_fecha_limite']);
        });

        Schema::table('motivos', function (Blueprint $table) {
            $table->dropColumn('es_destino_reclasificacion');
        });
    }
};
