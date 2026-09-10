<?php

namespace App\Http\Controllers\Jefe;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Bandeja del Jefe Inmediato / Jefe de Área (Paso 2 y transversales:
 * retorno manual, cerrar sin retorno, marcar abandono, reconocer
 * observación de RRHH). Un mismo User puede aparecer acá tanto como
 * jefe inmediato como jefe de área — se muestran ambas colas.
 */
class PapeletaController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $porDecidir = Papeleta::where('estado', PendienteJefe::class)
            ->where(function ($q) use ($user) {
                $q->where('jefe_inmediato_id', $user->id)
                    ->orWhere('jefe_area_id', $user->id);
            })
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $observacionesRrhh = Papeleta::where('estado', ObservadaPorRrhh::class)
            ->where('jefe_inmediato_id', $user->id)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $enCurso = Papeleta::where('estado', AutorizadaYCorriendo::class)
            ->where('jefe_inmediato_id', $user->id)
            ->with(['trabajador', 'motivo'])
            ->latest()
            ->get();

        $sustentosPorRevisar = Papeleta::where('estado', RetornoPendienteSustento::class)
            ->where('jefe_inmediato_id', $user->id)
            ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
            ->with(['trabajador', 'motivo', 'sustentos'])
            ->latest()
            ->get();

        return view('jefe.papeletas.index', compact(
            'porDecidir',
            'observacionesRrhh',
            'enCurso',
            'sustentosPorRevisar',
        ));
    }

    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor']);

        return view('jefe.papeletas.show', compact('papeleta'));
    }
}
