<?php

namespace App\Http\Controllers\Trabajador;

use App\Actions\Papeleta\MarcarRetornoAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\MarcarRetornoRequest;
use App\Models\Papeleta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Paso 5, retorno normal del trabajador: foto + GPS + hora del
 * servidor. El retorno manual por falla de conectividad NO vive acá
 * — lo marca el jefe (ver Jefe\DecisionController::retornoManual()).
 */
class RetornoController extends Controller
{
    public function store(MarcarRetornoRequest $request, Papeleta $papeleta, MarcarRetornoAction $action): RedirectResponse
    {
        $fotoPath = $request->file('foto')->store('papeletas/retornos', 'local');

        try {
            $action->normal($papeleta, Auth::user(), [
                'foto_path' => $fotoPath,
                'latitud' => $request->input('latitud'),
                'longitud' => $request->input('longitud'),
            ]);
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('trabajador.papeletas.show', $papeleta)
            ->with('success', 'Retorno registrado.');
    }
}
