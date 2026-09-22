<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ausencia TEMPORAL de un jefe (vacaciones/permiso) — distinta de la
     * baja permanente, que sigue siendo `users.activo`. Mientras hay una
     * fila vigente (fecha_inicio <= hoy <= fecha_fin) para un jefe,
     * CrearPapeletaAction lo trata como no disponible y busca un
     * suplente en `jefes_suplentes` para (unidad, turno) del trabajador
     * — ver User::estaAusente() y JefeSuplente::candidatosPara().
     *
     * `tipo` es texto libre por ahora (un único valor real:
     * 'vacaciones_permiso') para no cerrar la puerta a otros tipos de
     * ausencia temporal (comisión de servicio, licencia) sin migración
     * nueva — no condiciona ninguna lógica todavía.
     *
     * Un jefe puede tener varias filas (ausencias en distintas fechas);
     * no hay unicidad de rango porque no se valida solapamiento aquí.
     */
    public function up(): void
    {
        Schema::create('ausencias_jefe', function (Blueprint $table) {
            $table->id();

            $table->foreignId('jefe_id')
                ->constrained('users')->cascadeOnDelete();

            $table->date('fecha_inicio');
            $table->date('fecha_fin');

            $table->string('tipo')->default('vacaciones_permiso');

            // Quién registró la ausencia (admin o el propio jefe de área) — auditoría.
            $table->foreignId('registrado_por_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['jefe_id', 'fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencias_jefe');
    }
};
