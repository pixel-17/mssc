<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuente de verdad del ciclo de un trabajador: qué turno del
     * catálogo tiene asignado (MANANA/TARDE/NOCHE para 728, DIA para
     * 276) y desde qué fecha arranca su bloque de "dias_trabajo"
     * seguidos + "dias_descanso". Una sola fila activa por trabajador
     * (unique en user_id): al cargar una actualización, se pisa esta
     * fila, no se acumulan versiones — el histórico de qué se generó
     * cada mes vive en `cargas_turno_mensuales`.
     *
     * El ciclo se ancla a una fecha, no al calendario: por eso no hace
     * falta ningún tratamiento especial para meses de 28/29/30/31
     * días — el mes siguiente simplemente continúa la cuenta de días
     * desde `fecha_ancla` (ver GeneradorTurnoMensualService).
     */
    public function up(): void
    {
        Schema::create('configuraciones_turno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('turno');
            $table->date('fecha_ancla');
            $table->unsignedTinyInteger('dias_trabajo')->default(6);
            $table->unsignedTinyInteger('dias_descanso')->default(1);
            $table->foreignId('actualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones_turno');
    }
};
