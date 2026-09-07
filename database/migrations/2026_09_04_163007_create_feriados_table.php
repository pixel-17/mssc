<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de feriados. Necesario porque el flujo habla de "48h hábiles"
     * (sustento de Salud) y "15 días hábiles" (subsanación de Emergencia) —
     * sin este catálogo esos plazos no se pueden calcular correctamente.
     */
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
