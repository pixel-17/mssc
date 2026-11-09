<?php

namespace App\Http\Controllers\Rrhh;

use App\Actions\Papeleta\AprobarRrhhAction;
use App\Actions\Papeleta\CorregirPapeletaRrhhAction;
use App\Actions\Papeleta\ObservarRrhhAction;
use App\Actions\Papeleta\RechazarRrhhAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Concerns\ResuelveUsuarioAutenticado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Http\Requests\Papeleta\CorregirPapeletaRequest;
use App\Models\Papeleta;
use App\Services\BloqueoRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Decisiones de RRHH (Paso 3: aprobar/rechazar/observar) + revisión
 * post-hoc (Paso 4). El abandono lo marca solo el sistema, nunca RRHH.
 */
class DecisionController extends Controller
{
    use ResuelveUsuarioAutenticado;

    public function aprobar(Papeleta $papeleta, AprobarRrhhAction $action, BloqueoRevisionService $bloqueo): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        try {
            $action->ejecutar($papeleta, $this->usuarioAutenticado());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        $bloqueo->liberar($papeleta, $this->usuarioAutenticado());

        return back()->with('success', 'Papeleta autorizada.');
    }

    public function rechazar(ComentarioRequest $request, Papeleta $papeleta, RechazarRrhhAction $action, BloqueoRevisionService $bloqueo): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        try {
            $action->ejecutar($papeleta, $this->usuarioAutenticado(), $request->string('comentario')->toString());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        $bloqueo->liberar($papeleta, $this->usuarioAutenticado());

        return back()->with('warning', 'Papeleta rechazada.');
    }

    public function observar(ComentarioRequest $request, Papeleta $papeleta, ObservarRrhhAction $action, BloqueoRevisionService $bloqueo): RedirectResponse
    {
        $this->authorize('decidirComoRrhh', $papeleta);

        try {
            $action->ejecutar($papeleta, $this->usuarioAutenticado(), $request->string('comentario')->toString());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        $bloqueo->liberar($papeleta, $this->usuarioAutenticado());

        return back()->with('success', 'Observación registrada, vuelve al jefe inmediato.');
    }

    public function posthocAprobar(Papeleta $papeleta, RevisionPosthocAction $action, BloqueoRevisionService $bloqueo): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        try {
            $action->aprobar($papeleta, $this->usuarioAutenticado());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        $bloqueo->liberar($papeleta, $this->usuarioAutenticado());

        return back()->with('success', 'Revisión post-hoc aprobada.');
    }

    public function posthocObservar(ComentarioRequest $request, Papeleta $papeleta, RevisionPosthocAction $action, BloqueoRevisionService $bloqueo): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        try {
            $action->observar($papeleta, $this->usuarioAutenticado(), $request->string('comentario')->toString());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        $bloqueo->liberar($papeleta, $this->usuarioAutenticado());

        return back()->with('success', 'Revisión post-hoc observada.');
    }

    public function posthocNoAprobar(ComentarioRequest $request, Papeleta $papeleta, RevisionPosthocAction $action, BloqueoRevisionService $bloqueo): RedirectResponse
    {
        $this->authorize('revisarPosthoc', $papeleta);

        try {
            $action->noAprobar($papeleta, $this->usuarioAutenticado(), $request->string('comentario')->toString());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        $bloqueo->liberar($papeleta, $this->usuarioAutenticado());

        return back()->with('success', 'Revisión post-hoc no aprobada: la papeleta pasó a Particular (con descuento).');
    }

    public function corregir(CorregirPapeletaRequest $request, Papeleta $papeleta, CorregirPapeletaRrhhAction $action, BloqueoRevisionService $bloqueo): RedirectResponse
    {
        $this->authorize('corregirComoRrhh', $papeleta);

        try {
            $action->ejecutar(
                $papeleta,
                $this->usuarioAutenticado(),
                $request->filled('hora_retorno') ? Carbon::parse($request->string('hora_retorno')->toString()) : null,
                $request->filled('motivo_id') ? $request->integer('motivo_id') : null,
                $request->string('comentario')->toString(),
            );
        } catch (PapeletaException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $bloqueo->liberar($papeleta, $this->usuarioAutenticado());

        return back()->with('success', 'Papeleta corregida. La corrección quedó en el historial.');
    }
}
