<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HorarioRrhhRequest;
use App\Models\HorarioRrhh;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Horario único de RRHH para toda la municipalidad, una fila por día de
 * semana (0=domingo...6=sábado, unique en la migración). No hay create
 * ni destroy: las 7 filas se siembran una sola vez con un seeder y de
 * ahí en adelante solo se editan.
 */
class HorarioRrhhController extends Controller
{
    public function index(): View
    {
        $horarios = HorarioRrhh::orderBy('dia_semana')->get();

        return view('admin.horarios-rrhh.index', compact('horarios'));
    }

    public function edit(HorarioRrhh $horarioRrhh): View
    {
        return view('admin.horarios-rrhh.edit', compact('horarioRrhh'));
    }

    public function update(HorarioRrhhRequest $request, HorarioRrhh $horarioRrhh): RedirectResponse
    {
        $horarioRrhh->update($request->validated());

        return redirect()->route('admin.horarios-rrhh.index')->with('status', 'Horario de RRHH actualizado.');
    }
}
