<?php

namespace App\Http\Controllers\Jefe;

use App\Actions\Papeleta\AprobarJefeAction;
use App\Actions\Papeleta\MarcarRetornoAction;
use App\Actions\Papeleta\ObservarJefeAction;
use App\Actions\Papeleta\RechazarJefeAction;
use App\Actions\Papeleta\ReconocerObservacionRrhhAction;
use App\Actions\Papeleta\ResponderPosthocAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Http\Requests\Papeleta\ObservarJefeRequest;
use App\Http\Requests\Papeleta\ResponderPosthocRequest;
use App\Http\Requests\Papeleta\RetornoManualRequest;
use App\Models\Papeleta;
use App\States\Papeleta\ObservadaPorRrhh;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
            $this->reabrirSiObservadaPorRrhh($papeleta, 'Aprobada directamente tras observación de RRHH.');
            $action->ejecutar($papeleta, Auth::user());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Papeleta aprobada.');
    }

    public function rechazar(ComentarioRequest $request, Papeleta $papeleta, RechazarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        try {
            $this->reabrirSiObservadaPorRrhh($papeleta, 'Rechazada directamente tras observación de RRHH.');
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('warning', 'Papeleta rechazada.');
    }

    public function observar(ObservarJefeRequest $request, Papeleta $papeleta, ObservarJefeAction $action): RedirectResponse
    {
        $this->authorize('decidirComoJefe', $papeleta);

        try {
            $this->reabrirSiObservadaPorRrhh($papeleta, 'Observada directamente tras observación de RRHH.');
            $action->ejecutar(
                $papeleta,
                Auth::user(),
                $request->input('comentario'),
                requiereAdjunto: $request->boolean('requiere_adjunto'),
            );
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

    /**
     * Responde la observación post-hoc de RRHH (solo el jefe que autorizó).
     * Igual que en el flujo del trabajador, un archivo nunca debe quedar
     * suelto en disco si la Action no llegó a referenciarlo.
     */
    public function responderPosthoc(ResponderPosthocRequest $request, Papeleta $papeleta, ResponderPosthocAction $action): RedirectResponse
    {
        $this->authorize('responderPosthoc', $papeleta);

        $archivoPath = $request->hasFile('archivo')
            ? $request->file('archivo')->store('papeletas/posthoc', 'local')
            : null;

        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('respuesta'), $archivoPath);
        } catch (PapeletaException $e) {
            $this->descartarAdjunto($archivoPath);

            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $this->descartarAdjunto($archivoPath);

            throw $e;
        }

        return redirect()
            ->route('jefe.papeletas.show', $papeleta)
            ->with('success', 'Respuesta enviada. RRHH volverá a revisar la papeleta.');
    }

    private function descartarAdjunto(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
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

    /**
     * Si RRHH observó la papeleta, el jefe decide directamente: antes de la decisión
     * se reabre en PENDIENTE_JEFE (mismo efecto que el antiguo «Reconocer»), con el
     * motivo de la decisión dejado en el historial.
     */
    private function reabrirSiObservadaPorRrhh(Papeleta $papeleta, string $motivo): void
    {
        $papeleta->refresh();

        if ($papeleta->estado->equals(ObservadaPorRrhh::class)) {
            app(ReconocerObservacionRrhhAction::class)->ejecutar($papeleta, Auth::user(), $motivo);
        }
    }
}