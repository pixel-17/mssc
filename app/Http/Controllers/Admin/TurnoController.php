<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TurnoRequest;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * El admin carga aquí la hora de CADA trabajador (CAS, 728, o jefes) por
 * fecha. Para CAS es obligatorio (TurnoRequest lo exige) para poder crear
 * papeleta ese día; para 728 es informativo. Un mismo trabajador no puede
 * tener dos filas para la misma fecha (unique en la migración y en la
 * validación).
 */
class TurnoController extends Controller
{
    public function index(Request $request): View
    {
        $turnos = Turno::with(['usuario', 'sede'])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->date('fecha')))
            ->orderByDesc('fecha')
            ->paginate(30)
            ->withQueryString();

        return view('admin.turnos.index', compact('turnos'));
    }

    public function create(): View
    {
        return view('admin.turnos.create');
    }

    public function store(TurnoRequest $request): RedirectResponse
    {
        Turno::create($request->validated());

        return redirect()->route('admin.turnos.index')->with('status', 'Turno cargado.');
    }

    public function edit(Turno $turno): View
    {
        return view('admin.turnos.edit', compact('turno'));
    }

    public function update(TurnoRequest $request, Turno $turno): RedirectResponse
    {
        $turno->update($request->validated());

        return redirect()->route('admin.turnos.index')->with('status', 'Turno actualizado.');
    }

    public function destroy(Turno $turno): RedirectResponse
    {
        $turno->delete();

        return redirect()->route('admin.turnos.index')->with('status', 'Turno eliminado.');
    }
}
