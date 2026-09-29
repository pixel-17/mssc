<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La revisión post-hoc deja de ser un callejón sin salida.
 *
 * Antes, "observada" era una marca final: nadie respondía y nadie se
 * enteraba. Ahora es un ida y vuelta entre RRHH y el jefe que autorizó:
 *
 *   pendiente ──(RRHH aprueba)──────────────► aprobada
 *   pendiente ──(RRHH observa)──► observada ──(jefe responde)──► respondida
 *   respondida ──(RRHH aprueba)─────────────► aprobada
 *   respondida ──(RRHH observa)─► observada …  (mismo tope que TOPE_OBSERVACIONES_RRHH)
 *   al alcanzar el tope (o si no hubo jefe que autorizara) ──► observada_firme
 *
 * - observada:        espera la respuesta del jefe que autorizó.
 * - respondida:       el jefe respondió; RRHH debe volver a revisar.
 * - observada_firme:  reparo definitivo de auditoría, sin más respuestas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->enum('revision_posthoc_estado', [
                'no_aplica',
                'pendiente',
                'observada',
                'respondida',
                'aprobada',
                'observada_firme',
            ])->default('no_aplica')->change();
        });

        Schema::table('papeletas', function (Blueprint $table) {
            $table->unsignedTinyInteger('contador_observaciones_posthoc')->default(0)->after('revision_posthoc_at');
            $table->text('posthoc_observacion')->nullable()->after('contador_observaciones_posthoc');
            $table->text('posthoc_respuesta')->nullable()->after('posthoc_observacion');
            $table->string('posthoc_adjunto_path')->nullable()->after('posthoc_respuesta');
            $table->timestamp('posthoc_respondida_at')->nullable()->after('posthoc_adjunto_path');
        });
    }

    public function down(): void
    {
        // Las observaciones que ya eran "finales" antes de este cambio
        // vuelven a ser simplemente "observada".
        DB::table('papeletas')
            ->whereIn('revision_posthoc_estado', ['respondida', 'observada_firme'])
            ->update(['revision_posthoc_estado' => 'observada']);

        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropColumn([
                'contador_observaciones_posthoc',
                'posthoc_observacion',
                'posthoc_respuesta',
                'posthoc_adjunto_path',
                'posthoc_respondida_at',
            ]);
        });

        Schema::table('papeletas', function (Blueprint $table) {
            $table->enum('revision_posthoc_estado', ['no_aplica', 'pendiente', 'aprobada', 'observada'])
                ->default('no_aplica')
                ->change();
        });
    }
};
