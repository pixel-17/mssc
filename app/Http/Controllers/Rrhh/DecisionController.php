<?php

namespace App\Http\Controllers\Rrhh;

use App\Actions\Papeleta\AprobarRrhhAction;
use App\Actions\Papeleta\MarcarAbandonoSobreRetornoPendienteAction;
use App\Actions\Papeleta\ObservarRrhhAction;
use App\Actions\Papeleta\RechazarRrhhAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Models\Papeleta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Decisiones de RRHH (Paso 3: aprobar/rechazar/observar) + revisión
 * post-hoc (Paso 4) + abandono sobre retorno pendiente de sustento,
 * que RRHH también puede resolver (ver PapeletaPolicy::marcarAbandono).
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

        return back()->with('success', 'Papeleta rechazada.');
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

    public function marcarAbandono(ComentarioRequest $request, Papeleta $papeleta, MarcarAbandonoSobreRetornoPendienteAction $action): RedirectResponse
    {
        $this->authorize('marcarAbandono', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Papeleta marcada como abandono no marcado.');
    }
}
