<?php

namespace App\Http\Controllers\Rrhh;

use App\Actions\Papeleta\AprobarRrhhAction;
use App\Actions\Papeleta\CorregirPapeletaRrhhAction;
use App\Actions\Papeleta\ObservarRrhhAction;
use App\Actions\Papeleta\RechazarRrhhAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Http\Requests\Papeleta\CorregirPapeletaRequest;
use App\Models\Papeleta;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Decisiones de RRHH (Paso 3: aprobar/rechazar/observar) + revisión
 * post-hoc (Paso 4). El abandono lo marca solo el sistema, nunca RRHH.
 */
class DecisionController extends Controller
{
    public function aprobar(Papeleta $papeleta, AprobarRrhhAction $action): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Papeleta autorizada.');
    }

    public function rechazar(ComentarioRequest $request, Papeleta $papeleta, RechazarRrhhAction $action): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('warning', 'Papeleta rechazada.');
    }

    public function observar(ComentarioRequest $request, Papeleta $papeleta, ObservarRrhhAction $action): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Observación registrada, vuelve al jefe inmediato.');
    }

    public function posthocAprobar(Papeleta $papeleta, RevisionPosthocAction $action): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        try {
            $action->aprobar($papeleta, Auth::user());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Revisión post-hoc aprobada.');
    }

    public function posthocObservar(ComentarioRequest $request, Papeleta $papeleta, RevisionPosthocAction $action): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        try {
            $action->observar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Revisión post-hoc observada.');
    }

    public function posthocNoAprobar(ComentarioRequest $request, Papeleta $papeleta, RevisionPosthocAction $action): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        try {
            $action->noAprobar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Revisión post-hoc no aprobada: la papeleta pasó a Particular (con descuento).');
    }

    public function corregir(CorregirPapeletaRequest $request, Papeleta $papeleta, CorregirPapeletaRrhhAction $action): RedirectResponse
    {
        $this->authorize('corregirComoRrhh', $papeleta);

        try {
            $action->ejecutar(
                $papeleta,
                Auth::user(),
                $request->filled('hora_retorno') ? Carbon::parse($request->input('hora_retorno')) : null,
                $request->filled('motivo_id') ? (int) $request->input('motivo_id') : null,
                $request->input('comentario'),
            );
        } catch (PapeletaException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Papeleta corregida. La corrección quedó en el historial.');
    }
}