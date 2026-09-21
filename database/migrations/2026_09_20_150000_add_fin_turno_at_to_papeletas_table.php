<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estados (valores persistidos) cuyo vencimiento/abandono todavía está
     * por decidirse: son los únicos que necesitan fin_turno_at. Las
     * papeletas terminales no lo usan.
     */
    private const ESTADOS_ACTIVOS = [
        'pendiente_jefe',
        'observada_por_jefe',
        'pendiente_rrhh',
        'observada_por_rrhh',
        'autorizada_y_corriendo',
    ];

    /**
     * fin_turno_at: instante real en que termina el turno (728, con cruce de
     * medianoche) o el horario ordinario (276) de la papeleta. Se fija al
     * crearla y los jobs de vencimiento y de abandono comparan contra él, en
     * vez de asumir que el día operativo termina a medianoche.
     *
     * Backfill de las papeletas que siguen en curso, para que el despliegue
     * no marque falsos abandonos:
     *  - 276: dia_operativo + HORARIO_ORDINARIO_HORA_FIN (todo el minuto).
     *  - 728: fin del turno cargado para su dia_operativo. Si no hay turno,
     *    queda NULL y DeterminadorFinDeTurno usa el criterio anterior.
     *
     * Las papeletas terminales quedan en NULL a propósito.
     */
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dateTime('fin_turno_at')->nullable()->after('dia_operativo');
            $table->index(['estado', 'fin_turno_at']);
        });

        $horaFin276 = DB::table('configuraciones')
            ->where('clave', 'HORARIO_ORDINARIO_HORA_FIN')
            ->value('valor') ?: '16:15';

        DB::table('papeletas')
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->whereNull('fin_turno_at')
            ->orderBy('id')
            ->chunkById(200, function ($filas) use ($horaFin276) {
                foreach ($filas as $fila) {
                    $fin = $fila->regimen === '728'
                        ? $this->finDeTurno728($fila)
                        : Carbon::parse($fila->dia_operativo)->setTimeFromTimeString($horaFin276)->endOfMinute();

                    if ($fin !== null) {
                        DB::table('papeletas')->where('id', $fila->id)->update([
                            'fin_turno_at' => $fin->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropIndex(['estado', 'fin_turno_at']);
            $table->dropColumn('fin_turno_at');
        });
    }

    private function finDeTurno728(object $papeleta): ?Carbon
    {
        $turno = DB::table('turnos')
            ->where('user_id', $papeleta->trabajador_id)
            ->whereDate('fecha', $papeleta->dia_operativo)
            ->where('es_descanso', false)
            ->whereNotNull('hora_inicio')
            ->whereNotNull('hora_fin')
            ->first();

        if (! $turno) {
            return null;
        }

        $fecha = Carbon::parse($papeleta->dia_operativo)->startOfDay();
        $inicio = $fecha->copy()->setTimeFromTimeString($turno->hora_inicio);
        $fin = $fecha->copy()->setTimeFromTimeString($turno->hora_fin);

        // Turno que cruza medianoche (Noche): termina al día siguiente.
        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin->addDay();
        }

        return $fin;
    }
};
