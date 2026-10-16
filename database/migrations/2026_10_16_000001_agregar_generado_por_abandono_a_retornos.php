<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuando ProcesarAbandonoNoMarcado cierra una papeleta sin que el
     * trabajador marcara su retorno, igual necesitamos UNA hora de fin
     * para poder calcular cuánto tiempo estuvo afuera (ver
     * HistorialTrabajadorService / ReporteHorasAcumuladasService, que ya
     * usan Retorno.hora_servidor como única fuente de verdad). Esa hora
     * es fin_turno_at, no una marcación real: este flag distingue ambos
     * casos para que la UI no diga "retorno registrado" cuando en
     * realidad es un abandono sin evidencia.
     */
    public function up(): void
    {
        Schema::table('retornos', function (Blueprint $table) {
            $table->boolean('generado_por_abandono')->default(false)->after('marcado_manual');
        });
    }

    public function down(): void
    {
        Schema::table('retornos', function (Blueprint $table) {
            $table->dropColumn('generado_por_abandono');
        });
    }
};
