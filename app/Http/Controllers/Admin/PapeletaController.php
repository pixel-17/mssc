<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use Illuminate\Contracts\View\View;

/**
 * Ficha de una papeleta para el admin: SOLO LECTURA, de cualquier estado.
 * Reutiliza la vista de RRHH con $soloLectura = true (ver la vista), así
 * no se duplica el detalle.
 */
class PapeletaController extends Controller
{
    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('rrhh.papeletas.show', [
            'papeleta' => $papeleta,
            'historialMes' => [],
            'soloLectura' => true,
        ]);
    }
}
