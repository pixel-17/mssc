<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La observación del jefe ahora SIEMPRE se responde por escrito; lo
     * que el jefe decide al observar es solo si además se exige un
     * adjunto. Por eso:
     *
     * - observacion_requiere_justificacion -> observacion_requiere_adjunto
     * - observacion_respuesta: el texto con el que el trabajador responde
     *   (lo ven jefe y RRHH).
     */
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->renameColumn('observacion_requiere_justificacion', 'observacion_requiere_adjunto');
            $table->text('observacion_respuesta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropColumn('observacion_respuesta');
            $table->renameColumn('observacion_requiere_adjunto', 'observacion_requiere_justificacion');
        });
    }
};
