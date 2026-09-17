<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProcesarVencimientoSustentos corre cada minuto y filtra siempre
 * por ('estado', 'fecha_limite') en las dos queries de su handle().
 * Además, 6 puntos del sistema (dashboards, bandejas de Jefe/RRHH)
 * consultan sustentos.estado vía whereHas(). Sin índice, todas esas
 * consultas barren la tabla completa; este índice compuesto cubre
 * ambos filtros directamente (estado como igualdad, fecha_limite
 * como rango, en ese orden).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sustentos', function (Blueprint $table) {
            $table->index(['estado', 'fecha_limite']);
        });
    }

    public function down(): void
    {
        Schema::table('sustentos', function (Blueprint $table) {
            $table->dropIndex(['estado', 'fecha_limite']);
        });
    }
};
