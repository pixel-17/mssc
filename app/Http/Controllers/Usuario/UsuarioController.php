<?php

namespace App\Http\Controllers\Usuario;

use App\Http\Controllers\Controller;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Listado de usuarios para Jefe de Área y Jefe Inmediato. Admin NO pasa
 * por aquí: gestiona usuarios desde App\Livewire\Usuarios\UsuarioAdminIndex/Form. RRHH tampoco: no crea
 * usuarios (ver UserPolicy).
 *
 * El alta y la edición (antes también en este controller) viven ahora
 * en App\Livewire\Usuarios\UsuarioForm (ver routes/web.php,
 * usuarios.crear / usuarios.editar): este controller solo resuelve el
 * listado.
 *
 * "Jefe de Área" y "Jefe Inmediato" no son roles de Spatie, son
 * posiciones relacionales — por eso el único middleware de estas
 * rutas es 'auth' y la autorización real vive en UserPolicy, igual
 * que en Jefe\PapeletaController.
 */
class UsuarioController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = Auth::user();

        // La admin no tiene trabajadores propios: gestiona cuentas desde su panel.
        if ($user->hasRole('admin')) {
            return redirect()->route('usuarios-admin.index');
        }
        $unidadIds = $this->subtreeIdsDeAreasQueEncabeza($user);

        $usuarios = $unidadIds->isNotEmpty()
            ? User::whereIn('unidad_organica_id', $unidadIds)->where('id', '!=', $user->id)->orderBy('name')->get()
            : $user->trabajadoresComoJefeInmediato();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'esJefeDeArea' => $unidadIds->isNotEmpty(),
            'puedeCrear' => $user->can('puedeCrearAlgo', User::class),
            'avisosJefatura' => $this->avisosDeUnidadesSinJefe($unidadIds),
        ]);
    }

    /**
     * Una línea por unidad activa del área que no tiene un jefe activo
     * (sin jefe asignado o con el jefe dado de baja).
     *
     * @return list<string>
     */
    private function avisosDeUnidadesSinJefe(Collection $unidadIds): array
    {
        if ($unidadIds->isEmpty()) {
            return [];
        }

        return UnidadOrganica::with('jefe')
            ->whereIn('id', $unidadIds)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->filter(fn (UnidadOrganica $unidad) => ! $unidad->jefe?->activo)
            ->map(fn (UnidadOrganica $unidad) => "{$unidad->nombre}: sin jefe activo.")
            ->values()
            ->all();
    }

    /**
     * IDs de todas las unidades (encabezadas + sub-unidades) donde
     * $user puede crear como Jefe de Área. Vacío si $user no encabeza
     * ninguna unidad (o sea, es "solo" Jefe Inmediato, o ni eso).
     */
    private function subtreeIdsDeAreasQueEncabeza(User $user): Collection
    {
        $ids = collect();

        foreach ($user->unidadesDeArea as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
