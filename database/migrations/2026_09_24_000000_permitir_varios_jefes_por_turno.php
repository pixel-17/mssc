<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('jefes_turno', function (Blueprint $table) {
            $table->dropUnique(['unidad_organica_id', 'turno']);
            $table->unique(['unidad_organica_id', 'turno', 'jefe_id'], 'jefes_turno_unidad_turno_jefe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jefes_turno', function (Blueprint $table) {
            $table->dropUnique('jefes_turno_unidad_turno_jefe_unique');
            $table->unique(['unidad_organica_id', 'turno']);
        });
    }
};
