<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\View\View;

/**
 * Bandeja de RRHH (Paso 3 + Paso 4). Único para toda la municipalidad
 * — no se filtra por jefe/unidad, solo por estado.
 */
class PapeletaController extends Controller
{
    public function index(): View
    {
        $porDecidir = Papeleta::where('estado', PendienteRrhh::class)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $posthocPendientes = Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
            ->where('revision_posthoc_estado', 'pendiente')
            ->with(['trabajador', 'motivo', 'resueltoPorJefe'])
            ->latest()
            ->get();

        $sustentosPorRevisar = Papeleta::where('estado', \App\States\Papeleta\RetornoPendienteSustento::class)
            ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
            ->with(['trabajador', 'motivo', 'sustentos'])
            ->latest()
            ->get();

        return view('rrhh.papeletas.index', compact('porDecidir', 'posthocPendientes', 'sustentosPorRevisar'));
    }

    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('rrhh.papeletas.show', compact('papeleta'));
    }
}
