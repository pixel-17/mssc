<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use App\Support\Minutos;
use Carbon\Carbon;
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
     * @param  array{trabajador_id?: int|null, buscar?: string|null, sede_id?: int|null, unidad_organica_id?: int|null, motivo_id?: int|null, regimen?: string|null, solo_con_descuento?: bool}  $filtros
     * @return Builder<Papeleta>
     */
    public function query(User $usuario, string $mes, array $filtros = []): Builder
    {
        [$inicio, $fin] = $this->rangoDelMes($mes);

        $query = Papeleta::query()
            ->with(['trabajador.sede', 'trabajador.unidadOrganica', 'motivo', 'retorno'])
            ->whereHas('retorno')
            ->whereBetween('dia_operativo', [$inicio->toDateString(), $fin->toDateString()]);

        if (! $usuario->hasRole('admin') && ! $usuario->hasRole('rrhh')) {
            $query->deEquipoDe($usuario);
        }

        if (! empty($filtros['trabajador_id'])) {
            $query->where('trabajador_id', $filtros['trabajador_id']);
        }

        // Búsqueda libre por nombre, apellido o DNI del trabajador.
        if (! empty($filtros['buscar'])) {
            $buscar = trim($filtros['buscar']);

            $query->whereHas('trabajador', fn (Builder $q) => $q->where(function (Builder $q2) use ($buscar) {
                $q2->where('name', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('dni', 'like', "%{$buscar}%");
            }));
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
     *
     * @param  array{trabajador_id?: int|null, buscar?: string|null, sede_id?: int|null, unidad_organica_id?: int|null, motivo_id?: int|null, regimen?: string|null, solo_con_descuento?: bool}  $filtros
     * @return Collection<int, array{papeleta_id: int, trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, dia: string, motivo: string, suma_descuento: bool, salida: \Illuminate\Support\Carbon|null, retorno: \Illuminate\Support\Carbon|null, minutos_brutos: int, minutos_refrigerio: int, minutos: int<0, max>}>
     */
    public function detalle(User $usuario, string $mes, array $filtros = []): Collection
    {
        return $this->query($usuario, $mes, $filtros)->get()->map(function (Papeleta $p) {
            $horaRetorno = $p->retorno?->hora_servidor;
            $minutosBrutos = ($p->hora_salida_real && $horaRetorno)
                ? Minutos::entre($p->hora_salida_real, $horaRetorno)
                : 0;

            return [
                'papeleta_id' => $p->id,
                'trabajador_id' => $p->trabajador_id,
                'trabajador' => (string) $p->trabajador->nombre_completo,
                'dni' => $p->trabajador->dni,
                'sede' => $p->trabajador->sede?->nombre,
                'unidad_organica' => $p->trabajador->unidadOrganica?->nombre,
                'dia' => $p->dia_operativo->format('Y-m-d'),
                'motivo' => $p->motivo->nombre,
                'suma_descuento' => (bool) $p->motivo->suma_descuento,
                'salida' => $p->hora_salida_real,
                'retorno' => $horaRetorno,
                'minutos_brutos' => $minutosBrutos,
                'minutos_refrigerio' => (int) $p->descuento_refrigerio_minutos,
                // Lo que va a planilla: el bloque de almuerzo dentro de la
                // ausencia no es tiempo laborable, se resta (ver MarcarRetornoAction).
                'minutos' => max(0, $minutosBrutos - (int) $p->descuento_refrigerio_minutos),
            ];
        });
    }

    /**
     * Agregado mensual por trabajador — responde directo la pregunta
     * "quién salió más / a quién le toca descuento": total de horas,
     * horas que sí descuentan (motivo->suma_descuento) y cantidad de
     * papeletas, ordenado de mayor a menor tiempo acumulado.
     *
     * Acepta el detalle ya calculado (ver ResumenPapeletasIndex::render,
     * que necesita este resumen Y el diario en la misma vista) para no
     * correr la misma query pesada dos veces; si no se pasa, la calcula.
     *
     * @param  array{trabajador_id?: int|null, buscar?: string|null, sede_id?: int|null, unidad_organica_id?: int|null, motivo_id?: int|null, regimen?: string|null, solo_con_descuento?: bool}  $filtros
     * @param  Collection<int, array{papeleta_id: int, trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, dia: string, motivo: string, suma_descuento: bool, salida: \Illuminate\Support\Carbon|null, retorno: \Illuminate\Support\Carbon|null, minutos_brutos: int, minutos_refrigerio: int, minutos: int<0, max>}>|null  $detalle
     * @return Collection<int, array{trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, papeletas: int, minutos_totales: int, minutos_con_descuento: int, papeletas_con_descuento: int}>
     */
    public function resumenPorTrabajador(User $usuario, string $mes, array $filtros = [], ?Collection $detalle = null): Collection
    {
        return ($detalle ?? $this->detalle($usuario, $mes, $filtros))
            ->groupBy('trabajador_id')
            ->map(function (Collection $filas) {
                /** @var Collection<int, array{papeleta_id: int, trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, dia: string, motivo: string, suma_descuento: bool, salida: \Illuminate\Support\Carbon|null, retorno: \Illuminate\Support\Carbon|null, minutos_brutos: int, minutos_refrigerio: int, minutos: int<0, max>}> $filas */
                $primera = $filas->first() ?? throw new \LogicException('groupBy() nunca produce un grupo vacío.');
                $conDescuento = $filas->where('suma_descuento', true);

                return [
                    'trabajador_id' => $primera['trabajador_id'],
                    'trabajador' => $primera['trabajador'],
                    'dni' => $primera['dni'],
                    'sede' => $primera['sede'],
                    'unidad_organica' => $primera['unidad_organica'],
                    'papeletas' => $filas->count(),
                    'minutos_totales' => (int) $filas->sum('minutos'),
                    'minutos_con_descuento' => (int) $conDescuento->sum('minutos'),
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
     *
     * Mismo criterio que resumenPorTrabajador: acepta el detalle ya
     * calculado para evitar repetir la query.
     *
     * @param  array{trabajador_id?: int|null, buscar?: string|null, sede_id?: int|null, unidad_organica_id?: int|null, motivo_id?: int|null, regimen?: string|null, solo_con_descuento?: bool}  $filtros
     * @param  Collection<int, array{papeleta_id: int, trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, dia: string, motivo: string, suma_descuento: bool, salida: \Illuminate\Support\Carbon|null, retorno: \Illuminate\Support\Carbon|null, minutos_brutos: int, minutos_refrigerio: int, minutos: int<0, max>}>|null  $detalle
     * @return Collection<int, array{trabajador_id: int, trabajador: string, dia: string, minutos: int, motivos: string}>
     */
    public function resumenDiarioPorTrabajador(User $usuario, string $mes, array $filtros = [], ?Collection $detalle = null): Collection
    {
        return ($detalle ?? $this->detalle($usuario, $mes, $filtros))
            ->groupBy(function ($fila) {
                /** @var array{trabajador_id: int, dia: string} $fila */
                return $fila['trabajador_id'].'|'.$fila['dia'];
            })
            ->map(function (Collection $filas) {
                /** @var Collection<int, array{papeleta_id: int, trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, dia: string, motivo: string, suma_descuento: bool, salida: \Illuminate\Support\Carbon|null, retorno: \Illuminate\Support\Carbon|null, minutos_brutos: int, minutos_refrigerio: int, minutos: int<0, max>}> $filas */
                $primera = $filas->first() ?? throw new \LogicException('groupBy() nunca produce un grupo vacío.');

                return [
                    'trabajador_id' => $primera['trabajador_id'],
                    'trabajador' => $primera['trabajador'],
                    'dia' => $primera['dia'],
                    'minutos' => (int) $filas->sum('minutos'),
                    'motivos' => $filas->pluck('motivo')->unique()->implode(', '),
                ];
            })
            ->sortBy('dia')
            ->values();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rangoDelMes(string $mes): array
    {
        // `mes` llega del cliente (propiedad Livewire): un valor mal formado
        // lanzaba una excepción de Carbon (500). Se degrada al mes actual.
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $mes = now()->format('Y-m');
        }

        $inicio = (Carbon::createFromFormat('Y-m-d', "{$mes}-01") ?: throw new \RuntimeException('Mes inválido.'))->startOfMonth();

        return [$inicio->copy(), $inicio->copy()->endOfMonth()];
    }
}