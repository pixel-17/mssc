<?php

namespace App\Http\Controllers\Trabajador;

use App\Actions\Papeleta\CancelarPapeletaAction;
use App\Actions\Papeleta\CrearPapeletaAction;
use App\Actions\Papeleta\SubsanarObservacionAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\StorePapeletaRequest;
use App\Http\Requests\Papeleta\SubsanarObservacionRequest;
use App\Models\Motivo;
use App\Models\Papeleta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Illuminate\View\View;

/**
 * Bandeja del Trabajador (Paso 1 del flujo): crear, ver el estado de
 * sus propias papeletas, cancelar mientras siga en PENDIENTE_JEFE u
 * OBSERVADA_POR_JEFE y responder por escrito (con adjunto si el jefe lo
 * exige) a las observaciones del jefe.
 */
class PapeletaController extends Controller
{
    public function index(): View
    {
        $papeletas = Auth::user()->papeletas()
            ->with(['motivo', 'sede', 'retorno'])
            ->latest()
            ->paginate(15);

        return view('trabajador.papeletas.index', compact('papeletas'));
    }

    public function create(): View
    {
        $this->authorize('crear', Papeleta::class);

        $motivos = Motivo::where('activo', true)->orderBy('nombre')->get();

        return view('trabajador.papeletas.create', compact('motivos'));
    }

    public function store(StorePapeletaRequest $request, CrearPapeletaAction $action): RedirectResponse
    {
        $motivo = Motivo::findOrFail($request->integer('motivo_id'));

        $adjuntoPath = $request->hasFile('adjunto_inicial_path')
            ? $request->file('adjunto_inicial_path')->store('papeletas/adjuntos-iniciales', 'local')
            : null;

        try {
            $papeleta = $action->ejecutar(Auth::user(), $motivo, [
                'justificacion' => $request->input('justificacion'),
                'adjunto_inicial_path' => $adjuntoPath,
                'hora_retorno_estimado' => $request->input('hora_retorno_estimado'),
            ]);
        } catch (PapeletaException $e) {
            $this->descartarAdjunto($adjuntoPath);

            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            $this->descartarAdjunto($adjuntoPath);

            throw $e;
        }

        return redirect()
            ->route('trabajador.papeletas.show', $papeleta)
            ->with('success', 'Papeleta creada correctamente.');
    }

    /** Si la Action falla el archivo ya está en disco: se borra para no dejarlo huérfano. */
    private function descartarAdjunto(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('trabajador.papeletas.show', compact('papeleta'));
    }

    /**
     * Responde a una observación del jefe: siempre por escrito, con
     * adjunto si el jefe lo exigió. Igual que en SustentoController, un
     * archivo nunca debe quedar suelto en disco si la Action no llegó a
     * referenciarlo.
     */
    public function subsanar(SubsanarObservacionRequest $request, Papeleta $papeleta, SubsanarObservacionAction $action): RedirectResponse
    {
        $archivoPath = $request->hasFile('archivo')
            ? $request->file('archivo')->store('papeletas/subsanaciones', 'local')
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
            ->route('trabajador.papeletas.show', $papeleta)
            ->with('success', 'Respuesta enviada. Tu jefe volverá a decidir sobre la papeleta.');
    }

    public function cancelar(Papeleta $papeleta, CancelarPapeletaAction $action): RedirectResponse
    {
        $this->authorize('cancelar', $papeleta);

        try {
            $action->ejecutar($papeleta, Auth::user());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('trabajador.papeletas.index')
            ->with('success', 'Papeleta cancelada.');
    }
}
