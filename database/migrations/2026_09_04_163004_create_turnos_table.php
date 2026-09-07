<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Para CAS: fila obligatoria por día para poder crear papeleta
     * (sin fila ese día = bloqueo total, salvo Emergencia).
     * Para 728: la tabla es opcional y no se usa para validar nada,
     * pero puede seguir sirviendo como referencia informativa de turno/descanso.
     * También se reutiliza para saber si un Jefe Inmediato / Jefe de Área
     * "está en su horario" al momento de escalar (Paso 2).
     */
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->boolean('es_descanso')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
