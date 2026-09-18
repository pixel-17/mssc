<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código explícito del turno (MANANA|TARDE|NOCHE|DIA) de cada fila.
     *
     * Hasta ahora el tipo de turno solo se deducía comparando
     * `hora_inicio` contra las horas vigentes en `configuraciones`
     * (ver Turno::etiqueta). Si alguien cambia esas horas, el
     * calendario dejaría de reconocer los turnos ya cargados. Con esta
     * columna el tipo queda guardado; las filas antiguas (NULL) siguen
     * funcionando por el método de deducción como respaldo.
     */
    public function up(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->string('turno', 10)->nullable()->after('es_descanso');
        });
    }

    public function down(): void
    {
        Schema::table('turnos', function (Blueprint $table) {
            $table->dropColumn('turno');
        });
    }
};
