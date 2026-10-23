<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Traduce una selección de días de la semana (1=lunes .. 7=domingo) al
 * ciclo fecha_ancla/dias_trabajo/dias_descanso que entiende
 * GeneradorTurnoMensualService — así "descansa sábado y domingo" se
 * sigue apoyando en el mismo ciclo anclado que ya usa el generador, sin
 * una tabla ni un recorrido de fechas aparte.
 *
 * Solo admite un arco CONTIGUO de días dentro de la semana (ej. sábado
 * y domingo, o lunes y martes): es lo único que un ciclo semanal puede
 * representar. Para descansos salteados (ej. martes y viernes) el jefe
 * sigue teniendo el selector de días de trabajo/descanso por número en
 * ConfiguracionTurnoForm.
 *
 * Usada por ConfiguracionTurnoForm (un trabajador a la vez) y por
 * CalendarioEquipoIndex (todo el régimen 276 del equipo de una vez).
 */
final class PatronDescansoSemanal
{
    /**
     * @param  array<int, int>  $diasSemana  1..7, sin repetir
     * @return array{fecha_ancla: Carbon, dias_trabajo: int, dias_descanso: int}|null  null si la selección no es válida
     */
    public static function anclaYCiclo(array $diasSemana, Carbon $referencia): ?array
    {
        $dias = array_values(array_unique(array_map('intval', $diasSemana)));

        if ($dias === [] || count($dias) >= 7 || ! self::esArcoContiguo($dias)) {
            return null;
        }

        sort($dias);

        // Primer día de trabajo: el que no está en $dias y cuyo día
        // anterior (circular, domingo->lunes) sí está.
        $primerDiaTrabajo = collect(range(1, 7))
            ->first(fn (int $d) => ! in_array($d, $dias, true) && in_array($d === 1 ? 7 : $d - 1, $dias, true));

        $ancla = $referencia->copy()->startOfWeek(Carbon::MONDAY);
        while ($ancla->dayOfWeekIso !== $primerDiaTrabajo) {
            $ancla->addDay();
        }

        return [
            'fecha_ancla' => $ancla,
            'dias_trabajo' => 7 - count($dias),
            'dias_descanso' => count($dias),
        ];
    }

    /** @param  array<int, int>  $dias  1..7, sin repetir, ya sin duplicados */
    public static function esArcoContiguo(array $dias): bool
    {
        for ($inicio = 1; $inicio <= 7; $inicio++) {
            $arco = collect(range(0, count($dias) - 1))
                ->map(fn (int $k) => (($inicio - 1 + $k) % 7) + 1)
                ->sort()
                ->values()
                ->all();

            if ($arco === $dias) {
                return true;
            }
        }

        return false;
    }
}