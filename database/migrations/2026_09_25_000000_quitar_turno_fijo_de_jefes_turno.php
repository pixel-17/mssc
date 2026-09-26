<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `jefes_turno` dejaba elegir a mano, al crear la fila, un turno fijo
 * (MANANA/TARDE/NOCHE) para el jefe inmediato — un bucket manual que
 * podía divergir de la programación real del jefe en
 * `configuraciones_turno` (la que sí usa Turno::vigenteParaUsuario
 * para saber si está "de servicio" hoy). Esa selección duplicada ya
 * no se pide: `jefes_turno` pasa a ser solo "este jefe es jefe
 * inmediato adicional de esta unidad (régimen 728)", y el turno que
 * cubre sale siempre de su propia configuración de calendario (ver
 * UnidadOrganica::resolverJefeInmediato() / resolverJefesInmediatos()
 * y User::scopeDeLosTurnosQueCubre()).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mismo motivo que la migración anterior (permitir_varios_jefes_por_turno):
        // ambos índices cuelgan de columnas con FK, así que ADD y DROP van juntos
        // en un solo ALTER TABLE para no quedarse sin índice que respalde la FK.
        DB::statement(
            'ALTER TABLE jefes_turno '.
            'ADD UNIQUE jefes_turno_unidad_jefe_unique (unidad_organica_id, jefe_id), '.
            'DROP INDEX jefes_turno_unidad_turno_jefe_unique'
        );

        Schema::table('jefes_turno', function (Blueprint $table) {
            $table->dropColumn('turno');
        });
    }

    public function down(): void
    {
        Schema::table('jefes_turno', function (Blueprint $table) {
            $table->string('turno', 10)->nullable()->after('unidad_organica_id');
        });

        DB::statement("UPDATE jefes_turno SET turno = 'MANANA' WHERE turno IS NULL");

        DB::statement(
            'ALTER TABLE jefes_turno '.
            'MODIFY turno VARCHAR(10) NOT NULL, '.
            'ADD UNIQUE jefes_turno_unidad_turno_jefe_unique (unidad_organica_id, turno, jefe_id), '.
            'DROP INDEX jefes_turno_unidad_jefe_unique'
        );
    }
};
