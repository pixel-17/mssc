<?php

use App\Models\Configuracion;
use Illuminate\Database\Migrations\Migration;

/**
 * TOPE_OBSERVACIONES (Jefe) y TOPE_OBSERVACIONES_RRHH (RR. HH.) aparecían
 * por separado en Configuraciones aunque ambas son el mismo concepto: el
 * número de observaciones antes del rechazo automático. Cada flujo sigue
 * con su propio contador (contador_observaciones / contador_observaciones_rrhh
 * en papeletas), pero el tope que ambos comparan ahora es uno solo.
 *
 * Si el admin ya había personalizado el de RRHH a un valor distinto del de
 * Jefe, se conserva ese valor (se asume el más reciente/intencional) en vez
 * de perderlo silenciosamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rrhh = Configuracion::where('clave', 'TOPE_OBSERVACIONES_RRHH')->first();

        if ($rrhh) {
            Configuracion::updateOrCreate(
                ['clave' => 'TOPE_OBSERVACIONES'],
                ['valor' => $rrhh->valor, 'descripcion' => 'Tope de observaciones (del Jefe y de RR. HH., cada uno con su propio contador) antes del rechazo automático.'],
            );

            $rrhh->delete();
        }
    }

    public function down(): void
    {
        $tope = Configuracion::where('clave', 'TOPE_OBSERVACIONES')->value('valor') ?? '3';

        Configuracion::updateOrCreate(
            ['clave' => 'TOPE_OBSERVACIONES_RRHH'],
            ['valor' => $tope, 'descripcion' => 'Tope de observaciones de RRHH antes del rechazo automático.'],
        );
    }
};
