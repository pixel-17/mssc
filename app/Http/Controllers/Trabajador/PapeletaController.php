<?php

namespace App\Http\Controllers\Trabajador;

use App\Actions\Papeleta\CancelarPapeletaAction;
use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\StorePapeletaRequest;
use App\Models\Motivo;
use App\Models\Papeleta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Bandeja del Trabajador (Paso 1 del flujo): crear, ver el estado de
 * sus propias papeletas y cancelar mientras siga en PENDIENTE_JEFE.
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
            ]);
        } catch (PapeletaException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('trabajador.papeletas.show', $papeleta)
            ->with('success', 'Papeleta creada correctamente.');
    }

    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('trabajador.papeletas.show', compact('papeleta'));
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
