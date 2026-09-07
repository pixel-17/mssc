<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MotivoRequest;
use App\Models\Motivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Los 4 motivos (Particular/Salud/Comisión/Emergencia) normalmente no se
 * crean nuevos en producción — este CRUD es para ajustar sus banderas de
 * negocio (adjunto, bypass, exclusividad, etc.) sin tocar código.
 */
class MotivoController extends Controller
{
    public function index(): View
    {
        $motivos = Motivo::orderBy('nombre')->paginate(20);

        return view('admin.motivos.index', compact('motivos'));
    }

    public function create(): View
    {
        return view('admin.motivos.create');
    }

    public function store(MotivoRequest $request): RedirectResponse
    {
        Motivo::create($request->validated());

        return redirect()->route('admin.motivos.index')->with('status', 'Motivo creado.');
    }

    public function edit(Motivo $motivo): View
    {
        return view('admin.motivos.edit', compact('motivo'));
    }

    public function update(MotivoRequest $request, Motivo $motivo): RedirectResponse
    {
        $motivo->update($request->validated());

        return redirect()->route('admin.motivos.index')->with('status', 'Motivo actualizado.');
    }

    public function destroy(Motivo $motivo): RedirectResponse
    {
        if ($motivo->papeletas()->exists()) {
            return back()->withErrors([
                'motivo' => 'No se puede eliminar: tiene papeletas asociadas. Desactívalo en su lugar.',
            ]);
        }

        $motivo->delete();

        return redirect()->route('admin.motivos.index')->with('status', 'Motivo eliminado.');
    }
}
