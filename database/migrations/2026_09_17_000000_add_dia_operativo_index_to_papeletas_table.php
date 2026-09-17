<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ReporteHorasAcumuladasService::query() (reporte de cierre de mes)
 * filtra siempre por whereBetween('dia_operativo', ...), sin pasar
 * por 'estado' primero — así que el índice compuesto
 * ['estado', 'dia_operativo'] que ya existe no lo cubre. Para
 * admin/RRHH, que ven a todo el personal (sin el filtro por
 * jefe_inmediato_id/jefe_area_id que sí está indexado por ser FK),
 * esa consulta terminaba barriendo la tabla completa en cada cambio
 * de mes. Este índice cubre ese rango de fechas directamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->index('dia_operativo');
        });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropIndex(['dia_operativo']);
        });
    }
};
