<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Cualquier reporte administrativo (App\Livewire\Reportes\*) usa este
 * trait para exigir el mismo criterio de acceso que el dashboard:
 * admin/RRHH ven todo el personal, jefe (inmediato o de área, no es
 * rol Spatie, ver UserPolicy::crearTrabajadorPropio) solo ve su propio
 * equipo — la frontera real de datos la aplica cada Service, esto
 * solo bloquea la entrada a quien no es ninguno de los tres.
 *
 * Antes este bloque estaba repetido igual en el mount() de cada
 * componente de Reportes; sacarlo a trait evita que una futura
 * corrección de la regla quede aplicada en unos y olvidada en otros.
 */
trait RestringeAReportes
{
    protected function autorizarAccesoAReportes(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole('admin') && ! $user->hasRole('rrhh') && ! $user->can('crearTrabajadorPropio', User::class)) {
            $this->redirectRoute('trabajador.papeletas.index');
        }
    }
}
