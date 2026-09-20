<?php

namespace App\Http\Controllers\Jefe;

use App\Http\Controllers\Controller;
use App\Models\Papeleta;
use Illuminate\View\View;

/**
 * Detalle de una papeleta para el Jefe Inmediato / Jefe de Área. El
 * listado vive en App\Livewire\Papeletas\JefeIndex (se re-renderiza solo
 * con las notificaciones en vivo); este controller solo resuelve la
 * ficha de una papeleta puntual.
 */
class PapeletaController extends Controller
{
    public function show(Papeleta $papeleta): View
    {
        $this->authorize('view', $papeleta);

        $papeleta->load(['motivo', 'sede', 'trabajador', 'retorno', 'sustentos', 'historial.actor', 'jefeInmediato', 'jefeArea']);

        return view('jefe.papeletas.show', compact('papeleta'));
    }
}
