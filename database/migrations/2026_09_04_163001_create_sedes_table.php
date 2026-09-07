<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si el proyecto P_Salida ya tiene una tabla `sedes`, esta migración es
     * solo referencia — ajusta/omite y en su lugar crea una migración
     * "add_gps_fields_to_sedes_table" con las columnas latitud/longitud/radio_metros,
     * que son las que usa la validación de GPS del retorno (Paso 5 del flujo).
     */
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->unsignedInteger('radio_metros')->default(150); // radio permitido para validar el retorno
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};
