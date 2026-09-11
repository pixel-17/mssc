<?php

namespace App\Http\Controllers\Usuario;

use App\Actions\Usuario\VincularJefeInmediatoAction;
use App\Exceptions\UsuarioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\BuscarPorDniRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Los trabajadores pueden tener más de un jefe inmediato: al vincular,
 * indicar que ya tiene y, si el jefe está de acuerdo, añadir más."
 *
 * Flujo en dos pasos:
 * 1. buscar(): localiza por DNI y muestra sus jefes actuales, SIN
 *    vincular nada todavía.
 * 2. vincular(): recién aquí, con confirmación explícita del checkbox,
 *    se inserta el vínculo.
 *
 * Solo tiene sentido para quien ya puede actuar como Jefe Inmediato
 * (automático o adicional de al menos un trabajador) o Jefe de Área —
 * ver UserPolicy::crearTrabajadorPropio().
 */
class VinculoController extends Controller
{
    public function buscar(BuscarPorDniRequest $request, VincularJefeInmediatoAction $action): View|RedirectResponse
    {
        $user = Auth::user();
        $this->authorize('crearTrabajadorPropio', User::class);

        $trabajador = $action->buscarPorDni($request->validated('dni'));

        if (! $trabajador) {
            return back()->withInput()->with('error', 'No se encontró ningún usuario con ese DNI.');
        }

        if ($user->esJefeInmediatoDe($trabajador)) {
            return back()->withInput()->with('error', 'Ya eres jefe inmediato de este trabajador.');
        }

        return view('usuarios.confirmar-vinculo', [
            'trabajador' => $trabajador,
            'jefesActuales' => $action->jefesActualesDe($trabajador),
        ]);
    }

    public function vincular(User $trabajador, VincularJefeInmediatoAction $action): RedirectResponse
    {
        $user = Auth::user();
        $this->authorize('crearTrabajadorPropio', User::class);

        try {
            $action->vincular($user, $trabajador, confirmado: request()->boolean('confirmado'));
        } catch (UsuarioException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('usuarios.index')
            ->with('success', "Ahora eres jefe inmediato de {$trabajador->nombre_completo}.");
    }
}
