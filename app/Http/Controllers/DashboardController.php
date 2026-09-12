<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Punto de entrada único tras login.
 *
 * El dashboard real (métricas, no bandeja de trabajo) es SOLO para
 * admin, RRHH y jefe (inmediato o de área) — "jefe" no es un rol de
 * Spatie, así que se detecta igual que en navigation-menu.blade.php:
 * vía la policy `crearTrabajadorPropio` (encabeza una unidad, o tiene
 * trabajadores propios automáticos/adicionales).
 *
 * El trabajador "plano" (sin ninguna de esas condiciones) NO tiene
 * dashboard: sigue yendo directo a su bandeja de papeletas, como
 * antes. Un mismo usuario puede calificar para varios paneles a la
 * vez (ej. admin que también es jefe de su propia unidad) — en ese
 * caso ve todas las secciones que le correspondan en una sola vista.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardMetricsService $metricas)
    {
    }

    public function index(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $esAdmin = $user->hasRole('admin');
        $esRrhh = $user->hasRole('rrhh');
        $esJefe = $user->can('crearTrabajadorPropio', User::class);

        if (! $esAdmin && ! $esRrhh && ! $esJefe) {
            return redirect()->route('trabajador.papeletas.index');
        }

        return view('dashboard', [
            'esAdmin' => $esAdmin,
            'esRrhh' => $esRrhh,
            'esJefe' => $esJefe,
            'admin' => $esAdmin ? $this->metricas->paraAdmin() : null,
            'rrhh' => $esRrhh ? $this->metricas->paraRrhh() : null,
            'jefe' => $esJefe ? $this->metricas->paraJefe($user) : null,
        ]);
    }
}
