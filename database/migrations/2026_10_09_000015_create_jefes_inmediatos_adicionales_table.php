<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jefes inmediatos ADICIONALES de un trabajador, asignados a mano.
     *
     * Esto es aparte de `users.jefe_inmediato_id`, que sigue siendo el
     * jefe "automático" derivado de la unidad orgánica del trabajador
     * (ver UserObserver) y que NO se toca con esta tabla.
     *
     * Un trabajador puede tener 0 o más filas aquí. Cuando se le va a
     * asignar un jefe adicional, la UI debe mostrar los jefes que ya
     * tiene (el automático + los que ya estén en esta tabla) y pedir
     * confirmación explícita antes de insertar uno nuevo.
     *
     * Para aprobar una papeleta, cualquiera de los jefes del trabajador
     * (el automático o cualquiera de los de aquí) puede decidir —
     * el que llegue primero (ver User::esJefeInmediatoDe()).
     */
    public function up(): void
    {
        Schema::create('jefes_inmediatos_adicionales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trabajador_id')
                ->constrained('users')->cascadeOnDelete();

            $table->foreignId('jefe_inmediato_id')
                ->constrained('users')->cascadeOnDelete();

            // Quién hizo la asignación (admin, jefe de área, u otro jefe
            // inmediato del mismo trabajador) — para auditoría.
            $table->foreignId('asignado_por_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['trabajador_id', 'jefe_inmediato_id'], 'jefes_inmediatos_adic_trabajador_jefe_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jefes_inmediatos_adicionales');
    }
};
