<?php

namespace App\Http\Controllers\Rrhh;

use App\Actions\Papeleta\RevisarSustentoAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Concerns\ResuelveUsuarioAutenticado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\RevisarSustentoRequest;
use App\Models\Sustento;
use Illuminate\Http\RedirectResponse;

class SustentoController extends Controller
{
    use ResuelveUsuarioAutenticado;

    public function revisar(RevisarSustentoRequest $request, Sustento $sustento, RevisarSustentoAction $action): RedirectResponse
    {
        $this->authorize('revisarSustento', $sustento);

        try {
            match ($request->input('resultado')) {
                'aprobado' => $action->aprobar($sustento, $this->usuarioAutenticado()),
                'particular' => $action->rechazar($sustento, $this->usuarioAutenticado(), $request->input('comentario')),
                default => $action->observar($sustento, $this->usuarioAutenticado(), $request->input('comentario')),
            };
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sustento revisado.');
    }
}
