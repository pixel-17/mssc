<?php

namespace App\Http\Controllers\Rrhh;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use Illuminate\View\View;

/**
 * Acciones de detalle de la bandeja de RRHH. El listado vive en
 * App\Livewire\Papeletas\RrhhIndex (se re-renderiza solo con las
 * notificaciones en vivo); este controller ya solo resuelve la ficha
 * de una papeleta puntual.
 */
class PapeletaController extends Controller
{
    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('rrhh.papeletas.show', compact('papeleta'));
    }
}
