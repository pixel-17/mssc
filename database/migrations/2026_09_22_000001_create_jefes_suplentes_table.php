<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jefe(s) suplente(s) para una unidad orgánica en un turno dado
     * ('MANANA'|'TARDE'|'NOCHE'|'DIA', mismos códigos que Turno::codigo()
     * / ConfiguracionTurno). Hasta N suplentes posibles por (unidad,
     * turno): `orden` define la prioridad con la que CrearPapeletaAction
     * los prueba cuando el jefe titular está en ausencia temporal (ver
     * ausencias_jefe) — se usa el primero que además esté disponible
     * (DecisorDisponibleService) y no esté él mismo ausente.
     *
     * Si ninguno está disponible, la papeleta NO cae al flujo de
     * RRHH/auto-autorización que ya existe para "jefe fuera de horario":
     * se queda en PENDIENTE_JEFE, fotografiando al titular ausente,
     * hasta que el job de vencimiento la marca Vencida.
     */
    public function up(): void
    {
        Schema::create('jefes_suplentes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unidad_organica_id')
                ->constrained('unidades_organicas')->cascadeOnDelete();

            $table->string('turno', 10);

            $table->foreignId('jefe_suplente_id')
                ->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('orden')->default(0);

            $table->timestamps();

            $table->unique(
                ['unidad_organica_id', 'turno', 'jefe_suplente_id'],
                'jefes_suplentes_unidad_turno_jefe_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jefes_suplentes');
    }
};
