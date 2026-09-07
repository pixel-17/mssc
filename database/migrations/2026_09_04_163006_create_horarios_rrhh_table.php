<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horario único de RRHH para toda la municipalidad, por día de semana.
     * Se consulta en cada aprobación de jefe para decidir si la papeleta
     * va a PENDIENTE_RRHH o directo a AUTORIZADA_Y_CORRIENDO (Paso 2),
     * y para armar la bandeja de revisión post-hoc (Paso 4).
     */
    public function up(): void
    {
        Schema::create('horarios_rrhh', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('dia_semana'); // 0 = domingo ... 6 = sábado
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('activo')->default(true); // false = RRHH no atiende ese día
            $table->timestamps();

            $table->unique('dia_semana');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_rrhh');
    }
};
