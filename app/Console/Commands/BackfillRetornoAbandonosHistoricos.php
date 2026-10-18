<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\MarcarRetornoAction;
use App\Models\Papeleta;
use App\Models\Retorno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Uso único (no entra al schedule): papeletas de abandono que quedaron
 * Finalizada/Cerrada/EnJustificacion ANTES de que
 * ProcesarAbandonoNoMarcado empezara a crear el Retorno con la hora de
 * fin de turno. Sin este backfill, el historial y el reporte de horas
 * acumuladas siguen sin poder calcular esos casos viejos.
 *
 * Solo toca papeletas con fin_turno_at ya guardado (el fin se decide una
 * sola vez al crearlas, ver DeterminadorFinDeTurno); las legadas sin esa
 * columna no se tocan porque su "fin" dependía del horario vigente en su
 * momento, no se puede reconstruir con certeza acá, y se listan aparte
 * para revisión manual.
 *
 * --dry-run: solo cuenta y lista, no escribe nada.
 */
class BackfillRetornoAbandonosHistoricos extends Command
{
    protected $signature = 'papeletas:backfill-retorno-abandonos {--dry-run : Solo cuenta y lista, no escribe nada}';

    protected $description = 'Crea el Retorno (hora de fin de turno, generado_por_abandono) para abandonos ya resueltos antes de este cambio.';

    public function handle(MarcarRetornoAction $marcarRetorno): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidatas = Papeleta::where('causa_finalizacion_sin_retorno', 'abandono_no_marcado')
            ->whereDoesntHave('retorno');

        $sinFinTurno = (clone $candidatas)->whereNull('fin_turno_at')->count();
        $aCorregir = (clone $candidatas)->whereNotNull('fin_turno_at');

        $total = $aCorregir->count();
        $this->info("Papeletas a corregir (con fin_turno_at): {$total}.");

        if ($sinFinTurno > 0) {
            $this->warn("{$sinFinTurno} papeletas de abandono sin fin_turno_at (legadas): no se tocan, revisar a mano.");
            $this->table(['id', 'trabajador_id', 'dia_operativo', 'regimen'], Papeleta::where('causa_finalizacion_sin_retorno', 'abandono_no_marcado')
                ->whereDoesntHave('retorno')
                ->whereNull('fin_turno_at')
                ->get(['id', 'trabajador_id', 'dia_operativo', 'regimen'])
                ->map(fn (Papeleta $p) => [$p->id, $p->trabajador_id, $p->dia_operativo->toDateString(), $p->regimen])
                ->all());
        }

        if ($dryRun) {
            $this->table(['id', 'trabajador_id', 'dia_operativo', 'fin_turno_at'], $aCorregir->get(['id', 'trabajador_id', 'dia_operativo', 'fin_turno_at'])
                ->map(fn (Papeleta $p) => [$p->id, $p->trabajador_id, $p->dia_operativo->toDateString(), $p->fin_turno_at])
                ->all());

            return self::SUCCESS;
        }

        $corregidas = 0;

        $aCorregir->chunkById(100, function ($lote) use ($marcarRetorno, &$corregidas) {
            foreach ($lote as $papeleta) {
                DB::transaction(function () use ($papeleta, $marcarRetorno, &$corregidas) {
                    $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->first();

                    if (! $actual || $actual->retorno()->exists() || ! $actual->fin_turno_at) {
                        return;
                    }

                    Retorno::create([
                        'papeleta_id' => $actual->id,
                        'hora_servidor' => $actual->fin_turno_at,
                        'generado_por_abandono' => true,
                    ]);

                    $actual->descuento_refrigerio_minutos = $marcarRetorno->calcularDescuentoRefrigerio($actual, $actual->fin_turno_at);
                    $actual->save();

                    $corregidas++;
                });
            }
        });

        $this->info("Listo: {$corregidas} papeletas corregidas.");

        return self::SUCCESS;
    }
}
