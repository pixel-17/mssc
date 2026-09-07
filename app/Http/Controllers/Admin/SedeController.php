<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SedeRequest;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SedeController extends Controller
{
    public function index(): View
    {
        $sedes = Sede::orderBy('nombre')->paginate(20);

        return view('admin.sedes.index', compact('sedes'));
    }

    public function create(): View
    {
        return view('admin.sedes.create');
    }

    public function store(SedeRequest $request): RedirectResponse
    {
        Sede::create($request->validated());

        return redirect()->route('admin.sedes.index')->with('status', 'Sede creada.');
    }

    public function edit(Sede $sede): View
    {
        return view('admin.sedes.edit', compact('sede'));
    }

    public function update(SedeRequest $request, Sede $sede): RedirectResponse
    {
        $sede->update($request->validated());

        return redirect()->route('admin.sedes.index')->with('status', 'Sede actualizada.');
    }

    public function destroy(Sede $sede): RedirectResponse
    {
        if ($sede->usuarios()->exists() || $sede->papeletas()->exists()) {
            return back()->withErrors([
                'sede' => 'No se puede eliminar: tiene trabajadores o papeletas asociadas. Desactívala en su lugar.',
            ]);
        }

        $sede->delete();

        return redirect()->route('admin.sedes.index')->with('status', 'Sede eliminada.');
    }
}
