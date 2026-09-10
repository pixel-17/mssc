<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Representa todo el organigrama (Concejo, Gerencia Municipal, Gerencias
     * de línea, Sub Gerencias, Oficinas Generales, Oficinas de apoyo/
     * asesoramiento/control) como un único árbol auto-referenciado.
     *
     * No hay límite de niveles ni de cantidad de nodos: cualquier unidad
     * puede tener sub-oficinas creadas en cualquier momento sin tocar
     * el motor de papeletas.
     *
     * Regla de escalamiento (Sección 1 del flujo), derivada del árbol y NO
     * de `tipo`: Jefe Inmediato = jefe_id de la unidad del trabajador.
     * Jefe de Área = jefe_id de la unidad padre de esa unidad.
     */
    public function up(): void
    {
        Schema::create('unidad_organicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');

            // Solo para pintar el organigrama con los mismos colores de la
            // leyenda del documento fuente. NO se usa para la lógica de
            // escalamiento (esa es siempre relativa a la posición en el árbol).
            $table->enum('tipo', [
                'alta_direccion',
                'consultivo',
                'control',
                'apoyo',
                'apoyo_alcaldia',
                'asesoramiento',
                'linea_2do_nivel',
                'linea_3er_nivel',
            ])->nullable();

            $table->foreignId('parent_id')->nullable()
                ->constrained('unidad_organicas')->nullOnDelete();

            // Quien encabeza esta unidad. Nullable porque una unidad puede
            // crearse antes de asignarle jefe.
            $table->foreignId('jefe_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unidad_organica_id')->nullable()->after('jefe_area_id')
                ->constrained('unidad_organicas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unidad_organica_id');
        });

        Schema::dropIfExists('unidad_organicas');
    }
};