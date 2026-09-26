<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 1 del flujo "varios jefes deciden, el primero que actúa gana"
 * para régimen 728 (ver create_jefes_turno_table para el contexto
 * original de la tabla).
 *
 * Antes: unique(unidad_organica_id, turno) — un solo jefe por turno,
 * asignación fija.
 *
 * Ahora: unique(unidad_organica_id, turno, jefe_id) — varios jefes
 * pueden compartir el mismo turno; solo se evita duplicar la misma
 * fila exacta. Cada jefe sigue necesitando su propio ciclo (turno +
 * días trabajo/descanso + fecha ancla) en `configuraciones_turno`
 * (misma tabla que ya usan los trabajadores, con su propio user_id)
 * para que UnidadOrganica::resolverJefesInmediatos() sepa si está
 * "de servicio" hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests): no tiene la restricción de MySQL/InnoDB de abajo
        // (ni siquiera admite combinar ADD/DROP en un ALTER TABLE — cada
        // índice se agrega/borra con su propia sentencia), así que el
        // camino simple con Schema:: alcanza.
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('jefes_turno', function (Blueprint $table) {
                $table->unique(['unidad_organica_id', 'turno', 'jefe_id'], 'jefes_turno_unidad_turno_jefe_unique');
                $table->dropUnique('jefes_turno_unidad_organica_id_turno_unique');
            });

            return;
        }

        // Ambos índices empiezan por `unidad_organica_id`, que tiene una FK
        // hacia unidad_organicas. MySQL/InnoDB no deja borrar un índice si
        // es el único que respalda esa FK (error 1553), así que ADD y DROP
        // van en un solo ALTER TABLE: MySQL evalúa ambas cláusulas juntas y
        // en ningún momento se queda sin índice que cubra la FK.
        DB::statement(
            'ALTER TABLE jefes_turno '.
            'ADD UNIQUE jefes_turno_unidad_turno_jefe_unique (unidad_organica_id, turno, jefe_id), '.
            'DROP INDEX jefes_turno_unidad_organica_id_turno_unique'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('jefes_turno', function (Blueprint $table) {
                $table->unique(['unidad_organica_id', 'turno'], 'jefes_turno_unidad_organica_id_turno_unique');
                $table->dropUnique('jefes_turno_unidad_turno_jefe_unique');
            });

            return;
        }

        DB::statement(
            'ALTER TABLE jefes_turno '.
            'ADD UNIQUE jefes_turno_unidad_organica_id_turno_unique (unidad_organica_id, turno), '.
            'DROP INDEX jefes_turno_unidad_turno_jefe_unique'
        );
    }
};