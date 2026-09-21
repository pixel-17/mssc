<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retira el escalamiento por SLA del Jefe Inmediato al Jefe de Área: la
 * responsabilidad de decidir una papeleta es SIEMPRE del Jefe Inmediato,
 * nunca pasa a otro por inacción. Si el jefe inmediato no decide, la
 * papeleta sigue esperando hasta que el job de vencimiento la marca
 * Vencida al terminar el turno/día (regla que NO cambia: el sistema
 * nunca autoriza por inacción).
 *
 * - papeletas: columna escalado_jefe_area_at.
 * - configuraciones: RELOJ_JEFE_MINUTOS.
 *
 * NO se toca: papeletas.jefe_area_id ni users.jefe_area_id, que siguen
 * vivos para reportes/dashboards (Papeleta::scopeDeEquipoDe) y para
 * asignar jefes inmediatos adicionales (AsignarJefeAdicionalAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones')->where('clave', 'RELOJ_JEFE_MINUTOS')->delete();

        if (Schema::hasColumn('papeletas', 'escalado_jefe_area_at')) {
            Schema::table('papeletas', function (Blueprint $table) {
                $table->dropColumn('escalado_jefe_area_at');
            });
        }
    }

    /**
     * Restaura la columna (vacía: los timestamps de escalamiento no
     * vuelven) y la fila de configuración con su valor por defecto.
     */
    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->timestamp('escalado_jefe_area_at')->nullable();
        });

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'RELOJ_JEFE_MINUTOS'],
            ['valor' => '5', 'descripcion' => 'Minutos que tiene el Jefe Inmediato para decidir antes de escalar o vencer.'],
        );
    }
};
