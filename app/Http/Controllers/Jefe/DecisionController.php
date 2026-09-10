<?php

namespace App\Http\Controllers\Jefe;

use App\Actions\Papeleta\AprobarJefeAction;
use App\Actions\Papeleta\MarcarAbandonoSobreRetornoPendienteAction;
use App\Actions\Papeleta\MarcarRetornoAction;
use App\Actions\Papeleta\ObservarJefeAction;
use App\Actions\Papeleta\RechazarJefeAction;
use App\Actions\Papeleta\ReconocerObservacionRrhhAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Http\Requests\Papeleta\RetornoManualRequest;
use App\Models\Papeleta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Todas las decisiones del Jefe sobre una papeleta (Paso 2 + los
 * carriles que solo el jefe puede resolver: retorno manual, cierre
 * sin retorno físico, abandono sobre retorno pendiente de sustento,
 * reconocer observación de RRHH).
 */
class DecisionController extends Controller
{
    public function aprobar(Papeleta $papeleta, AprobarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user(), $this->actorTipo($papeleta));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Papeleta aprobada.');
    }

    public function rechazar(ComentarioRequest $request, Papeleta $papeleta, RechazarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'), $this->actorTipo($papeleta));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Papeleta rechazada.');
    }

    public function observar(ComentarioRequest $request, Papeleta $papeleta, ObservarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'), $this->actorTipo($papeleta));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Observación registrada.');
    }

    public function reconocerObservacionRrhh(ComentarioRequest $request, Papeleta $papeleta, ReconocerObservacionRrhhAction $action): RedirectResponse
    {
        $this->authorize('reconocerObservacionRrhh', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Observación de RRHH reconocida, papeleta reabierta para tu decisión.');
    }

    public function retornoManual(RetornoManualRequest $request, Papeleta $papeleta, MarcarRetornoAction $action): RedirectResponse
    {
        $this->authorize('marcarRetornoManual', $papeleta);

        try {
            $action->manual($papeleta, Auth::user(), $request->input('justificacion'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Retorno manual registrado por falla de conectividad.');
    }

    public function cerrarSinRetorno(Papeleta $papeleta, MarcarRetornoAction $action): RedirectResponse
    {
        $this->authorize('cerrarSinRetorno', $papeleta);

        try {
            $action->cerrarSinRetornoFisico($papeleta, Auth::user());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Papeleta cerrada sin retorno físico.');
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

    /**
     * Una vez escalada (escalado_jefe_area_at no nulo) quien decide es
     * el Jefe de Área, aunque siga siendo el mismo modelo User de
     * "jefe" — el historial necesita saber cuál de los dos actuó.
     */
    private function actorTipo(Papeleta $papeleta): string
    {
        if ($papeleta->escalado_jefe_area_at !== null && $papeleta->jefe_area_id === Auth::id()) {
            return 'jefe_area';
        }

        return 'jefe_inmediato';
    }
}
