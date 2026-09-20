<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Observación del Jefe con justificación opcional:
     *
     * - observacion_requiere_justificacion: el jefe decide, al observar,
     *   si el trabajador debe presentar un adjunto. Si es false, el
     *   trabajador solo ve la observación y el jefe decide después.
     * - observacion_adjunto_path / observacion_subsanada_at: el adjunto
     *   (y cuándo) con el que el trabajador subsanó. Lo ven jefe y RRHH.
     * - reloj_jefe_at: instante desde el que corre el SLA del jefe. Nulo
     *   = created_at. Se fija al reabrir la papeleta en PENDIENTE_JEFE
     *   (subsanación del trabajador, reconocimiento de una observación
     *   de RRHH); sin esto, una papeleta reabierta escalaba al Jefe de
     *   Área de inmediato porque su created_at ya era viejo.
     */
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->boolean('observacion_requiere_justificacion')->default(false);
            $table->string('observacion_adjunto_path')->nullable();
            $table->timestamp('observacion_subsanada_at')->nullable();
            $table->timestamp('reloj_jefe_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropColumn([
                'observacion_requiere_justificacion',
                'observacion_adjunto_path',
                'observacion_subsanada_at',
                'reloj_jefe_at',
            ]);
        });
    }
};
