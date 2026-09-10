<?php

namespace App\Http\Controllers\Trabajador;

use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\SubirSustentoRequest;
use App\Models\Sustento;
use Illuminate\Http\RedirectResponse;

/**
 * Paso 5, motivo Salud: el trabajador sube el archivo de sustento.
 * A propósito NO es un Action de app/Actions/Papeleta — subir el
 * archivo nunca cierra el caso por sí solo, solo dejar constancia de
 * que ya se presentó (ver RevisarSustentoAction, que sí vive en
 * Actions porque esa parte SÍ es una decisión humana sobre el flujo).
 */
class SustentoController extends Controller
{
    public function store(SubirSustentoRequest $request, Sustento $sustento): RedirectResponse
    {
        if ($sustento->estado !== 'pendiente') {
            return back()->with('error', 'Este sustento ya fue presentado o revisado.');
        }

        $archivoPath = $request->file('archivo')->store('papeletas/sustentos', 'local');

        $sustento->update([
            'archivo_path' => $archivoPath,
            'estado' => 'presentado',
            'presentado_at' => now(),
        ]);

        return redirect()
            ->route('trabajador.papeletas.show', $sustento->papeleta_id)
            ->with('success', 'Sustento presentado, queda a la espera de revisión.');
    }
}
