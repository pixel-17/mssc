<?php

namespace App\Http\Controllers\Papeleta;

use App\Actions\Papeleta\RevisarEmergenciaAction;
use App\Actions\Papeleta\SubsanarEmergenciaAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Models\Papeleta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Paso 6. RevisarEmergenciaAction ya resuelve internamente si quien
 * llama es jefe o RRHH (User::hasRole('rrhh') / esJefeInmediatoDe) —
 * por eso aprobar/observar comparten el mismo par de rutas para ambos
 * roles, sin prefijo jefe/rrhh (mismo criterio que las rutas
 * jefes-adicionales.*).
 */
class EmergenciaController extends Controller
{
    public function aprobar(Papeleta $papeleta, RevisarEmergenciaAction $action): RedirectResponse
    {
        try {
            $action->aprobar($papeleta, Auth::user());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Visto bueno registrado.');
    }

    public function observar(ComentarioRequest $request, Papeleta $papeleta, RevisarEmergenciaAction $action): RedirectResponse
    {
        try {
            $action->observar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Emergencia observada.');
    }

    public function subsanar(ComentarioRequest $request, Papeleta $papeleta, SubsanarEmergenciaAction $action): RedirectResponse
    {
        try {
            $action->ejecutar($papeleta, Auth::user(), $request->input('comentario'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Subsanación enviada.');
    }
}
