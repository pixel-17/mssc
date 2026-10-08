<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de movimientos de trabajadores entre unidades orgánicas
 * hechos desde el organigrama (arrastrar y soltar).
 *
 * Cada fila guarda quién movió, a quién, y la foto ANTES/DESPUÉS de
 * unidad, sede y jefes (inmediato y de área), más cuándo (created_at).
 *
 * Es de solo agregar, con una única excepción: al deshacer un movimiento
 * se marca el original (`deshecho_at` / `deshecho_por_id`) y se agrega
 * una fila nueva que apunta al original con `revierte_id`. Nunca se
 * borra ni se reescribe el resto de columnas.
 *
 * Los FK son nullOnDelete: si luego se borra un usuario o una unidad,
 * el historial se conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_organigrama', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabajador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('unidad_anterior_id')->nullable()->constrained('unidad_organicas')->nullOnDelete();
            $table->foreignId('unidad_nueva_id')->nullable()->constrained('unidad_organicas')->nullOnDelete();

            $table->foreignId('sede_anterior_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->foreignId('sede_nueva_id')->nullable()->constrained('sedes')->nullOnDelete();

            $table->foreignId('jefe_inmediato_anterior_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jefe_inmediato_nuevo_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jefe_area_anterior_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('jefe_area_nuevo_id')->nullable()->constrained('users')->nullOnDelete();

            // Al mover a un trabajador se puede decidir quitarle sus jefes
            // inmediatos adicionales: se guardan aquí ({jefe_id, asignado_por_id})
            // para devolvérselos si se deshace. null = no se quitó ninguno.
            $table->json('jefes_adicionales_quitados')->nullable();

            // Si esta fila es la reversión de otra, apunta a la original.
            $table->foreignId('revierte_id')->nullable()->constrained('movimientos_organigrama')->nullOnDelete();

            // Se llena en la fila ORIGINAL cuando alguien la deshace.
            $table->timestamp('deshecho_at')->nullable();
            $table->foreignId('deshecho_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['trabajador_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_organigrama');
    }
};
