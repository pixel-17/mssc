<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfiguracionRequest;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Parámetros del sistema (reloj del jefe, tope de observaciones, bloque
 * de almuerzo, horas de sustento, días de subsanación). No se crean
 * nuevas claves desde acá en operación normal, solo se edita el `valor`
 * de las que ya trae el seeder — por eso no hay destroy.
 */
class ConfiguracionController extends Controller
{
    public function index(): View
    {
        $configuraciones = Configuracion::orderBy('clave')->get();

        return view('admin.configuraciones.index', compact('configuraciones'));
    }

    public function edit(Configuracion $configuracion): View
    {
        return view('admin.configuraciones.edit', compact('configuracion'));
    }

    public function update(ConfiguracionRequest $request, Configuracion $configuracion): RedirectResponse
    {
        $configuracion->update($request->validated());

        return redirect()->route('admin.configuraciones.index')->with('status', 'Configuración actualizada.');
    }
}
