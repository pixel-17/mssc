<?php

namespace App\Http\Controllers\UnidadOrganica;

use App\Actions\UnidadOrganica\CrearOficinaConJefeAction;
use App\Exceptions\UsuarioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UnidadOrganica\CrearOficinaRequest;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Models\Sede;
use App\Models\UnidadOrganica as UnidadOrganicaModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Autoservicio de árbol para Jefe de Área (ver
 * CrearOficinaConJefeAction para el detalle de la regla de negocio).
 * Admin NO necesita pasar por acá para sembrar la raíz — eso sigue
 * siendo UnidadOrganicaForm — pero puede usar este flujo también si
 * quiere crear una oficina con su jefe de una sola vez.
 *
 * Mismo patrón que UsuarioController: sin middleware de rol, la
 * autorización real vive en UserPolicy::crearOficina.
 */
class OficinaController extends Controller
{
    public function create(): View
    {
        $user = Auth::user();
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        abort_unless($user->hasRole('admin') || $unidadIds->isNotEmpty(), 403);

        return view('unidad-organica.crear-oficina', [
            'unidadesPadre' => $user->hasRole('admin')
                ? UnidadOrganicaModel::orderBy('nombre')->pluck('nombre', 'id')
                : UnidadOrganicaModel::whereIn('id', $unidadIds)->orderBy('nombre')->pluck('nombre', 'id'),
            'tipos' => UnidadOrganicaForm::TIPOS,
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(CrearOficinaRequest $request, CrearOficinaConJefeAction $action): RedirectResponse
    {
        try {
            $oficina = $action->ejecutar(
                creador: Auth::user(),
                datosOficina: $request->only('nombre', 'tipo', 'parent_id'),
                datosJefes: $request->input('jefes'),
            );
        } catch (UsuarioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('usuarios.index')
            ->with('success', "Oficina \"{$oficina->nombre}\" creada con su jefe inmediato.");
    }

    /** Mismo helper que UsuarioController::subtreeIdsDeAreasQueEncabeza(). */
    private function subtreeIdsDeAreasQueEncabeza($user): \Illuminate\Support\Collection
    {
        $ids = collect();

        foreach ($user->unidadesQueEncabeza as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
