<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnidadOrganicaRequest;
use App\Models\UnidadOrganica;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD del organigrama. Cualquier unidad puede recibir sub-oficinas
 * (parent_id) sin límite de niveles — ver App\Models\UnidadOrganica.
 * La validación anti-ciclos (no asignarse a sí misma ni a un descendiente
 * como padre) vive en UnidadOrganicaRequest.
 */
class UnidadOrganicaController extends Controller
{
    public function index(): View
    {
        $unidades = UnidadOrganica::with(['padre', 'jefe'])
            ->orderBy('nombre')
            ->paginate(20);

        return view('admin.unidades-organicas.index', compact('unidades'));
    }

    public function create(): View
    {
        $unidades = UnidadOrganica::orderBy('nombre')->get();

        return view('admin.unidades-organicas.create', compact('unidades'));
    }

    public function store(UnidadOrganicaRequest $request): RedirectResponse
    {
        UnidadOrganica::create($request->validated());

        return redirect()
            ->route('admin.unidades-organicas.index')
            ->with('status', 'Unidad orgánica creada.');
    }

    public function edit(UnidadOrganica $unidadOrganica): View
    {
        $unidades = UnidadOrganica::whereNotIn('id', array_merge(
            [$unidadOrganica->id],
            $unidadOrganica->descendantIds(),
        ))->orderBy('nombre')->get();

        return view('admin.unidades-organicas.edit', compact('unidadOrganica', 'unidades'));
    }

    public function update(UnidadOrganicaRequest $request, UnidadOrganica $unidadOrganica): RedirectResponse
    {
        $unidadOrganica->update($request->validated());

        return redirect()
            ->route('admin.unidades-organicas.index')
            ->with('status', 'Unidad orgánica actualizada.');
    }

    public function destroy(UnidadOrganica $unidadOrganica): RedirectResponse
    {
        if ($unidadOrganica->hijos()->exists()) {
            return back()->withErrors([
                'unidad' => 'No se puede eliminar: tiene sub-oficinas. Reasígnalas o elimínalas primero.',
            ]);
        }

        if ($unidadOrganica->miembros()->exists()) {
            return back()->withErrors([
                'unidad' => 'No se puede eliminar: tiene trabajadores asignados.',
            ]);
        }

        $unidadOrganica->delete();

        return redirect()
            ->route('admin.unidades-organicas.index')
            ->with('status', 'Unidad orgánica eliminada.');
    }
}
