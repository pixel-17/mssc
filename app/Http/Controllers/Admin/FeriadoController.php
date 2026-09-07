<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeriadoRequest;
use App\Models\Feriado;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FeriadoController extends Controller
{
    public function index(): View
    {
        $feriados = Feriado::orderByDesc('fecha')->paginate(30);

        return view('admin.feriados.index', compact('feriados'));
    }

    public function create(): View
    {
        return view('admin.feriados.create');
    }

    public function store(FeriadoRequest $request): RedirectResponse
    {
        Feriado::create($request->validated());

        return redirect()->route('admin.feriados.index')->with('status', 'Feriado registrado.');
    }

    public function edit(Feriado $feriado): View
    {
        return view('admin.feriados.edit', compact('feriado'));
    }

    public function update(FeriadoRequest $request, Feriado $feriado): RedirectResponse
    {
        $feriado->update($request->validated());

        return redirect()->route('admin.feriados.index')->with('status', 'Feriado actualizado.');
    }

    public function destroy(Feriado $feriado): RedirectResponse
    {
        $feriado->delete();

        return redirect()->route('admin.feriados.index')->with('status', 'Feriado eliminado.');
    }
}
