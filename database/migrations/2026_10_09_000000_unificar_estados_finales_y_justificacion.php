<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estados finales unificados y justificación con plazo por motivo.
 *
 *  - Terminales: `cerrada` (sin descuento) y `finalizada` (con descuento).
 *    Desaparecen `finalizado_sin_retorno` y `reclasificado_a_particular`.
 *  - `retorno_pendiente_sustento` pasa a `en_justificacion`, que también
 *    cubre al trabajador que abandonó y justifica después.
 *  - `causa_finalizacion_sin_retorno` deja de ser enum (hace falta el valor
 *    nuevo `salud_no_justificada`) y pasa a string.
 *  - `motivos.plazo_justificacion_horas_habiles`: plazo propio del motivo;
 *    si es null se usa la configuración global SUSTENTO_HORAS_HABILES.
 *
 * Los datos se convierten una sola vez; down() restaura los nombres de
 * estado pero no vuelve a poner la columna de causa como enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->string('causa_finalizacion_sin_retorno', 40)->nullable()->change();
        });

        Schema::table('motivos', function (Blueprint $table) {
            $table->unsignedSmallInteger('plazo_justificacion_horas_habiles')->nullable()->after('requiere_sustento_en_retorno');
        });

        DB::table('papeletas')
            ->where('estado', 'retorno_pendiente_sustento')
            ->update(['estado' => 'en_justificacion']);

        // Comisión de servicio cerrada sin retorno físico: sin descuento -> Cerrada.
        DB::table('papeletas')
            ->where('estado', 'finalizado_sin_retorno')
            ->where('causa_finalizacion_sin_retorno', 'comision_servicio_campo')
            ->update(['estado' => 'cerrada']);

        // Cualquier otro finalizado_sin_retorno que quedara (abandono): con descuento.
        DB::table('papeletas')
            ->where('estado', 'finalizado_sin_retorno')
            ->update(['estado' => 'finalizada']);

        DB::table('papeletas')
            ->where('estado', 'reclasificado_a_particular')
            ->update(['estado' => 'finalizada', 'causa_finalizacion_sin_retorno' => 'salud_no_justificada']);

        // Cerradas de motivos que descuentan (Particular, abandonos de Particular) -> Finalizada.
        $motivosConDescuento = DB::table('motivos')->where('suma_descuento', true)->pluck('id');

        if ($motivosConDescuento->isNotEmpty()) {
            DB::table('papeletas')
                ->where('estado', 'cerrada')
                ->whereIn('motivo_id', $motivosConDescuento)
                ->update(['estado' => 'finalizada']);
        }
    }

    public function down(): void
    {
        DB::table('papeletas')
            ->where('estado', 'finalizada')
            ->where('causa_finalizacion_sin_retorno', 'salud_no_justificada')
            ->update(['estado' => 'reclasificado_a_particular', 'causa_finalizacion_sin_retorno' => null]);

        DB::table('papeletas')->where('estado', 'finalizada')->update(['estado' => 'cerrada']);
        DB::table('papeletas')->where('estado', 'en_justificacion')->update(['estado' => 'retorno_pendiente_sustento']);

        Schema::table('motivos', function (Blueprint $table) {
            $table->dropColumn('plazo_justificacion_horas_habiles');
        });
    }
};
