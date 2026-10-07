<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use App\Services\ReporteHorasAcumuladasService;
use App\States\Papeleta\Rechazada;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Acciones de detalle de la bandeja de RRHH. El listado vive en
 * App\Livewire\Papeletas\RrhhIndex (se re-renderiza solo con las
 * notificaciones en vivo); este controller ya solo resuelve la ficha
 * de una papeleta puntual.
 */
class PapeletaController extends Controller
{
    public function show(Request $request, Papeleta $papeleta, ReporteHorasAcumuladasService $reporte): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        $historialMes = $this->historialDelMes($request, $papeleta, $reporte);

        return view('rrhh.papeletas.show', compact('papeleta', 'historialMes'));
    }

    /**
     * Resumen del mes de la papeleta para el trabajador, para que RRHH
     * decida sin salir a otro reporte. Excluye la papeleta que se está
     * mirando. Los minutos salen del mismo servicio que el reporte de
     * horas acumuladas, así que los totales cuadran con él.
     *
     * @return array{mes: string, papeletas: int, rechazadas: int, observaciones: int, minutos: int, minutos_con_descuento: int}
     */
    private function historialDelMes(Request $request, Papeleta $papeleta, ReporteHorasAcumuladasService $reporte): array
    {
        $mes = $papeleta->dia_operativo->format('Y-m');
        [$inicio, $fin] = $reporte->rangoDelMes($mes);

        $delMes = Papeleta::query()
            ->where('trabajador_id', $papeleta->trabajador_id)
            ->whereKeyNot($papeleta->getKey())
            ->whereBetween('dia_operativo', [$inicio->toDateString(), $fin->toDateString()]);

        $detalle = $reporte->detalle($request->user(), $mes, ['trabajador_id' => $papeleta->trabajador_id])
            ->reject(fn (array $fila) => $fila['papeleta_id'] === $papeleta->getKey());

        return [
            'mes' => $mes,
            'papeletas' => (clone $delMes)->count(),
            'rechazadas' => (clone $delMes)->whereState('estado', Rechazada::class)->count(),
            'observaciones' => (int) (clone $delMes)->sum('contador_observaciones_jefe')
                + (int) (clone $delMes)->sum('contador_observaciones_rrhh'),
            'minutos' => (int) $detalle->sum('minutos'),
            'minutos_con_descuento' => (int) $detalle->where('suma_descuento', true)->sum('minutos'),
        ];
    }
}
