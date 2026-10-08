<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jefes inmediatos ADICIONALES de una unidad orgánica para régimen 728.
     * Varios jefes pueden cubrir la misma unidad; el primero que actúa gana.
     *
     * El turno que cubre cada jefe NO se guarda aquí: sale de su propio ciclo
     * en `configuraciones_turno` (ver UnidadOrganica::resolverJefesInmediatos()
     * y User::scopeDeLosTurnosQueCubre()).
     *
     * `unidad_organicas.jefe_id` sigue siendo el jefe titular (jefe de área de
     * las unidades hijas y fallback de jefe inmediato para 276).
     */
    public function up(): void
    {
        Schema::create('jefes_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_organica_id')->constrained('unidad_organicas')->cascadeOnDelete();
            $table->foreignId('jefe_id')->constrained('users');
            $table->timestamps();

            $table->unique(['unidad_organica_id', 'jefe_id'], 'jefes_turno_unidad_jefe_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jefes_turno');
    }
};
