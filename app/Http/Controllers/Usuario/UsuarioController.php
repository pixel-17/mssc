<?php

namespace App\Http\Controllers\Usuario;

use App\Actions\Usuario\CrearUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\CrearUsuarioRequest;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Alta de usuarios para Jefe de Área y Jefe Inmediato. Admin NO pasa
 * por aquí: gestiona usuarios desde el UserResource de Filament (ver
 * comentario en User::canAccessPanel()). RRHH tampoco: no crea
 * usuarios (ver UserPolicy).
 *
 * "Jefe de Área" y "Jefe Inmediato" no son roles de Spatie, son
 * posiciones relacionales — por eso el único middleware de estas
 * rutas es 'auth' y la autorización real vive en UserPolicy, igual
 * que en Jefe\PapeletaController.
 */
class UsuarioController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        $usuarios = $unidadIds->isNotEmpty()
            ? User::whereIn('unidad_organica_id', $unidadIds)->where('id', '!=', $user->id)->orderBy('name')->get()
            : $user->trabajadoresComoJefeInmediato();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'esJefeDeArea' => $unidadIds->isNotEmpty(),
            'puedeCrear' => $user->can('puedeCrearAlgo', User::class),
        ]);
    }

    public function create(): View
    {
        $user = Auth::user();
        $this->authorize('puedeCrearAlgo', User::class);

        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        return view('usuarios.crear', [
            'esJefeDeArea' => $unidadIds->isNotEmpty(),
            'unidades' => $unidadIds->isNotEmpty()
                ? UnidadOrganica::whereIn('id', $unidadIds)->orderBy('nombre')->get()
                : collect(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(CrearUsuarioRequest $request, CrearUsuarioAction $action): RedirectResponse
    {
        $user = Auth::user();
        $esJefeDeArea = $request->unidadesDisponibles()->isNotEmpty();

        if ($esJefeDeArea) {
            $this->authorize('crearEnUnidad', [User::class, (int) $request->validated('unidad_organica_id')]);
        } else {
            $this->authorize('crearTrabajadorPropio', User::class);
        }

        try {
            $nuevo = $action->ejecutar($user, [...$request->validated(), 'tipo' => $request->input('tipo', 'trabajador')], $esJefeDeArea);
        } catch (UsuarioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario {$nuevo->nombre_completo} creado correctamente.");
    }

    /**
     * IDs de todas las unidades (encabezadas + sub-unidades) donde
     * $user puede crear como Jefe de Área. Vacío si $user no encabeza
     * ninguna unidad (o sea, es "solo" Jefe Inmediato, o ni eso).
     */
    private function subtreeIdsDeAreasQueEncabeza(User $user): \Illuminate\Support\Collection
    {
        $ids = collect();

        foreach ($user->unidadesQueEncabeza as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
