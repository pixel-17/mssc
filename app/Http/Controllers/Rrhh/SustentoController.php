<?php

namespace App\Http\Controllers\Rrhh;

use App\Actions\Papeleta\RevisarSustentoAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\RevisarSustentoRequest;
use App\Models\Sustento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SustentoController extends Controller
{
    public function revisar(RevisarSustentoRequest $request, Sustento $sustento, RevisarSustentoAction $action): RedirectResponse
    {
        $this->authorize('revisarSustento', $sustento);

        try {
            if ($request->input('resultado') === 'aprobado') {
                $action->aprobar($sustento, Auth::user());
            } else {
                $action->observar($sustento, Auth::user(), $request->input('comentario'));
            }
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sustento revisado.');
    }
}
