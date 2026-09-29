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
        // SQLite (tests): no admite ADD UNIQUE / DROP INDEX dentro de un
        // ALTER TABLE, así que cada índice se crea/borra con su propia
        // sentencia vía Schema::. Sin la restricción de FK de MySQL/InnoDB,
        // el orden entre ADD y DROP es libre.
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('jefes_turno', function (Blueprint $table) {
                $table->unique(['unidad_organica_id', 'jefe_id'], 'jefes_turno_unidad_jefe_unique');
                $table->dropUnique('jefes_turno_unidad_turno_jefe_unique');
            });
        } else {
            // Mismo motivo que la migración anterior (permitir_varios_jefes_por_turno):
            // ambos índices cuelgan de columnas con FK, así que ADD y DROP van juntos
            // en un solo ALTER TABLE para no quedarse sin índice que respalde la FK.
            DB::statement(
                'ALTER TABLE jefes_turno '.
                'ADD UNIQUE jefes_turno_unidad_jefe_unique (unidad_organica_id, jefe_id), '.
                'DROP INDEX jefes_turno_unidad_turno_jefe_unique'
            );
        }

        // Va después de borrar el índice viejo: SQLite no deja borrar una
        // columna que todavía forma parte de un índice.
        Schema::table('jefes_turno', function (Blueprint $table) {
            $table->dropColumn('turno');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('jefes_turno', function (Blueprint $table) {
                $table->string('turno', 10)->default('MANANA');
            });

            Schema::table('jefes_turno', function (Blueprint $table) {
                $table->unique(['unidad_organica_id', 'turno', 'jefe_id'], 'jefes_turno_unidad_turno_jefe_unique');
                $table->dropUnique('jefes_turno_unidad_jefe_unique');
            });

            return;
        }

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