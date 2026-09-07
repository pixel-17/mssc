<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Todo número que el documento marca como "parametrizable" vive acá,
     * nunca hardcodeado: reloj del jefe (5 min), tope de observaciones (3),
     * bloque de almuerzo (13:00-14:00), horas de sustento (48h hábiles),
     * días de subsanación de Emergencia (15 días hábiles).
     */
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('valor');
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
