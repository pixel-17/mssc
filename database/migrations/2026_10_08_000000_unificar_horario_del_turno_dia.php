<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CLAVES = ['TURNO_DIA_HORA_INICIO', 'TURNO_DIA_HORA_FIN'];

    /**
     * El turno Día (276) ya no tiene claves propias: usa el horario ordinario
     * global (HORARIO_ORDINARIO_HORA_INICIO/FIN), que es la ventana que bloquea
     * en HorarioOrdinarioService. Si ambas habían divergido, manda el horario
     * ordinario (es lo que aplicaba la regla de papeletas).
     */
    public function up(): void
    {
        DB::table('configuraciones')->whereIn('clave', self::CLAVES)->delete();

        foreach (self::CLAVES as $clave) {
            Cache::forget("configuracion:{$clave}");
        }
    }

    public function down(): void
    {
        foreach (['INICIO', 'FIN'] as $extremo) {
            $valor = DB::table('configuraciones')->where('clave', "HORARIO_ORDINARIO_HORA_{$extremo}")->value('valor');

            DB::table('configuraciones')->updateOrInsert(
                ['clave' => "TURNO_DIA_HORA_{$extremo}"],
                [
                    'valor' => $valor ?? ($extremo === 'INICIO' ? '07:45' : '16:15'),
                    'descripcion' => "Hora de ".($extremo === 'INICIO' ? 'inicio' : 'fin')." del turno Día (régimen 276, ciclo 6x1).",
                ],
            );
        }
    }
};
