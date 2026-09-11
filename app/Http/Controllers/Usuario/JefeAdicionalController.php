<?php

namespace App\Http\Controllers\Usuario;

use App\Actions\Usuario\AsignarJefeAdicionalAction;
use App\Actions\Usuario\DesasignarJefeAdicionalAction;
use App\Exceptions\PapeletaException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * jefes_inmediatos_adicionales: única puerta de escritura sobre esa
 * tabla. La autorización real (admin / jefe de área / jefe inmediato
 * ya existente del trabajador) vive en las Actions, no aquí — mismo
 * criterio que el grupo de rutas 'jefe' en routes/web.php.
 */
class JefeAdicionalController extends Controller
{
    public function store(Request $request, User $trabajador, AsignarJefeAdicionalAction $action): RedirectResponse
    {
        $request->validate([
            'jefe_id' => ['required', 'integer', 'exists:users,id'],
            'confirmado' => ['required', 'accepted'],
        ]);

        $jefeNuevo = User::findOrFail($request->integer('jefe_id'));

        try {
            $action->ejecutar($trabajador, $jefeNuevo, Auth::user(), (bool) $request->boolean('confirmado'));
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jefe inmediato adicional asignado.');
    }

    public function destroy(User $trabajador, User $jefe, DesasignarJefeAdicionalAction $action): RedirectResponse
    {
        try {
            $action->ejecutar($trabajador, $jefe, Auth::user());
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jefe inmediato adicional removido.');
    }
}
