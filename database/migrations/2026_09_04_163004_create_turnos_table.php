<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /**
      * Informativa para todos los regímenes desde el rediseño de 276:
      * ya no es obligatoria fila por día (276 valida contra el horario
      * único global de HorarioOrdinarioService/Configuraciones). Para
      * 728 sigue siendo la referencia opcional de turno/descanso, y es
      * la que efectivamente se consulta al escalar (Paso 2) cuando el
      * actor (Jefe Inmediato / Jefe de Área) es régimen 728.
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
