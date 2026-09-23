<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jefe INMEDIATO de una unidad orgánica, por turno de régimen 728
 * ('MANANA'|'TARDE'|'NOCHE' — ver ConfiguracionTurno::TURNOS_728).
 * Hasta 3 filas por unidad, una por turno. Asignación fija y MANUAL:
 * si el jefe de un turno falta (vacaciones, permiso, lo que sea), un
 * admin reasigna esta fila a otro jefe — no hay ausencia ni suplente
 * automático (se descartó a propósito, ver Avance 00.58 -> 00.59).
 *
 * Esto es SOLO para jefe inmediato. Jefe de área sigue siendo
 * `unidades_organicas.jefe_id` de la unidad padre, sin turno — no
 * cambia con esta tabla.
 *
 * `unidades_organicas.jefe_id` se mantiene como el jefe "titular" de
 * la unidad (usado por jefeArea() de los hijos, y como fallback de
 * jefe inmediato para 276 o para un turno sin fila propia aquí, ver
 * UnidadOrganica::resolverJefeInmediato()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jefes_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_organica_id')->constrained('unidad_organicas')->cascadeOnDelete();
            $table->string('turno', 10);
            $table->foreignId('jefe_id')->constrained('users');
            $table->timestamps();

            $table->unique(['unidad_organica_id', 'turno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jefes_turno');
    }
};
