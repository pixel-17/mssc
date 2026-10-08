<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisión post-hoc: RRHH puede NO aprobarla. En ese caso la papeleta se
     * reclasifica a Particular (con descuento) y la revisión queda en
     * 'no_aprobada' (ver RevisionPosthocAction::noAprobar).
     */
    private const VALORES = ['no_aplica', 'pendiente', 'observada', 'respondida', 'aprobada', 'observada_firme'];

    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->enum('revision_posthoc_estado', [...self::VALORES, 'no_aprobada'])
                ->default('no_aplica')
                ->change();
        });
    }

    public function down(): void
    {
        // Las filas 'no_aprobada' vuelven a 'observada_firme' (el reparo definitivo más parecido).
        \Illuminate\Support\Facades\DB::table('papeletas')
            ->where('revision_posthoc_estado', 'no_aprobada')
            ->update(['revision_posthoc_estado' => 'observada_firme']);

        Schema::table('papeletas', function (Blueprint $table) {
            $table->enum('revision_posthoc_estado', self::VALORES)
                ->default('no_aplica')
                ->change();
        });
    }
};
