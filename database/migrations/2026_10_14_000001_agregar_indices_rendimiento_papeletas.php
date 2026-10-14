<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices compuestos para las bandejas y el badge del sidebar, que se
     * recalculan en vivo (cada PapeletaActualizada): filtran por jefe + estado
     * y por el estado de la revisión post-hoc, y hasta ahora solo contaban con
     * el índice simple de la llave foránea.
     */
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            if (! Schema::hasIndex('papeletas', 'papeletas_jefe_inmediato_estado_index')) {
                $table->index(['jefe_inmediato_id', 'estado'], 'papeletas_jefe_inmediato_estado_index');
            }

            if (! Schema::hasIndex('papeletas', 'papeletas_jefe_area_estado_index')) {
                $table->index(['jefe_area_id', 'estado'], 'papeletas_jefe_area_estado_index');
            }

            if (! Schema::hasIndex('papeletas', 'papeletas_posthoc_estado_index')) {
                $table->index(['autorizado_con_rrhh_fuera_horario', 'revision_posthoc_estado'], 'papeletas_posthoc_estado_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropIndex('papeletas_jefe_inmediato_estado_index');
            $table->dropIndex('papeletas_jefe_area_estado_index');
            $table->dropIndex('papeletas_posthoc_estado_index');
        });
    }
};
