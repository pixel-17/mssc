<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporte administrativo (no el dashboard de métricas en vivo): a fin
 * de mes, admin/RRHH/jefe necesitan saber cuántas horas acumuló cada
 * trabajador fuera de sede (para el descuento de planilla) y qué
 * motivo las originó. Fuente de verdad: Papeleta.hora_salida_real ->
 * Retorno.hora_servidor, la misma pareja de columnas que ya usa
 * MarcarRetornoAction para calcular el descuento de refrigerio.
 *
 * Solo entran papeletas con retorno ya registrado (whereHas('retorno'))
 * — una papeleta todavía en curso no tiene horas "acumuladas" hasta
 * que cierra, así que no corresponde contarla aún.
 *
 * Misma frontera de visibilidad que DashboardMetricsService::paraJefe:
 * el jefe (inmediato o de área) solo ve su propio equipo, vía las
 * columnas fotografiadas en la papeleta (jefe_inmediato_id /
 * jefe_area_id), no por relación en vivo.
 */
class ReporteHorasAcumuladasService
{
    /**
     * @param  array{trabajador_id?: int|null, sede_id?: int|null, unidad_organica_id?: int|null, motivo_id?: int|null, regimen?: string|null, solo_con_descuento?: bool}  $filtros
     */
    public function query(User $usuario, string $mes, array $filtros = []): Builder
    {
        [$inicio, $fin] = $this->rangoDelMes($mes);

        $query = Papeleta::query()
            ->with(['trabajador.sede', 'trabajador.unidadOrganica', 'motivo', 'retorno'])
            ->whereHas('retorno')
            ->whereBetween('dia_operativo', [$inicio->toDateString(), $fin->toDateString()]);

        if (! $usuario->hasRole('admin') && ! $usuario->hasRole('rrhh')) {
            $query->where(function (Builder $q) use ($usuario) {
                $q->where('jefe_inmediato_id', $usuario->id)
                    ->orWhere('jefe_area_id', $usuario->id);
            });
        }

        if (! empty($filtros['trabajador_id'])) {
            $query->where('trabajador_id', $filtros['trabajador_id']);
        }

        if (! empty($filtros['sede_id'])) {
            $query->where('sede_id', $filtros['sede_id']);
        }

        if (! empty($filtros['unidad_organica_id'])) {
            $query->whereHas('trabajador', fn (Builder $q) => $q
                ->where('unidad_organica_id', $filtros['unidad_organica_id']));
        }

        if (! empty($filtros['motivo_id'])) {
            $query->where('motivo_id', $filtros['motivo_id']);
        }

        if (! empty($filtros['regimen'])) {
            $query->where('regimen', $filtros['regimen']);
        }

        if (! empty($filtros['solo_con_descuento'])) {
            $query->whereHas('motivo', fn (Builder $q) => $q->where('suma_descuento', true));
        }

        return $query->orderBy('dia_operativo');
    }

    /**
     * Detalle fila por fila (una papeleta = una fila), listo para la
     * tabla de detalle diario y para el export.
     */
    public function detalle(User $usuario, string $mes, array $filtros = []): Collection
    {
        return $this->query($usuario, $mes, $filtros)->get()->map(fn (Papeleta $p) => [
            'papeleta_id' => $p->id,
            'trabajador_id' => $p->trabajador_id,
            'trabajador' => $p->trabajador->nombre_completo,
            'sede' => $p->trabajador->sede?->nombre,
            'unidad_organica' => $p->trabajador->unidadOrganica?->nombre,
            'dia' => $p->dia_operativo->format('Y-m-d'),
            'motivo' => $p->motivo->nombre,
            'suma_descuento' => (bool) $p->motivo->suma_descuento,
            'salida' => $p->hora_salida_real,
            'retorno' => $p->retorno->hora_servidor,
            'minutos' => $p->hora_salida_real && $p->retorno->hora_servidor
                ? $p->hora_salida_real->diffInMinutes($p->retorno->hora_servidor)
                : 0,
        ]);
    }

    /**
     * Agregado mensual por trabajador — responde directo la pregunta
     * "quién salió más / a quién le toca descuento": total de horas,
     * horas que sí descuentan (motivo->suma_descuento) y cantidad de
     * papeletas, ordenado de mayor a menor tiempo acumulado.
     */
    public function resumenPorTrabajador(User $usuario, string $mes, array $filtros = []): Collection
    {
        return $this->detalle($usuario, $mes, $filtros)
            ->groupBy('trabajador_id')
            ->map(function (Collection $filas) {
                $conDescuento = $filas->where('suma_descuento', true);

                return [
                    'trabajador_id' => $filas->first()['trabajador_id'],
                    'trabajador' => $filas->first()['trabajador'],
                    'sede' => $filas->first()['sede'],
                    'unidad_organica' => $filas->first()['unidad_organica'],
                    'papeletas' => $filas->count(),
                    'minutos_totales' => $filas->sum('minutos'),
                    'minutos_con_descuento' => $conDescuento->sum('minutos'),
                    'papeletas_con_descuento' => $conDescuento->count(),
                ];
            })
            ->values()
            ->sortByDesc('minutos_totales')
            ->values();
    }

    /**
     * Agregado diario por trabajador (matriz día x trabajador), para
     * la vista "por día y por mes" que pidió el usuario: cuántos
     * minutos acumuló cada trabajador cada día del mes.
     */
    public function resumenDiarioPorTrabajador(User $usuario, string $mes, array $filtros = []): Collection
    {
        return $this->detalle($usuario, $mes, $filtros)
            ->groupBy(fn (array $fila) => $fila['trabajador_id'].'|'.$fila['dia'])
            ->map(fn (Collection $filas) => [
                'trabajador_id' => $filas->first()['trabajador_id'],
                'trabajador' => $filas->first()['trabajador'],
                'dia' => $filas->first()['dia'],
                'minutos' => $filas->sum('minutos'),
                'motivos' => $filas->pluck('motivo')->unique()->implode(', '),
            ])
            ->sortBy('dia')
            ->values();
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function rangoDelMes(string $mes): array
    {
        $inicio = \Carbon\Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfMonth();

        return [$inicio->copy(), $inicio->copy()->endOfMonth()];
    }
}
