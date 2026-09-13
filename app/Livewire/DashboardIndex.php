<?php

namespace App\Livewire;

use App\Livewire\Concerns\EscuchaNotificacionesEnVivo;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Migrado de App\Http\Controllers\DashboardController@index a Livewire
 * puro para que los contadores (pendientes, en curso, emergencias...)
 * se actualicen solos en tiempo real — ver EscuchaNotificacionesEnVivo.
 *
 * Mismo criterio de acceso que el Controller original: admin/RRHH/jefe
 * ven el dashboard de métricas; el trabajador "plano" no tiene
 * dashboard y sigue yendo directo a su bandeja.
 */
#[Layout('layouts.app')]
class DashboardIndex extends Component
{
    use EscuchaNotificacionesEnVivo;

    public function mount(DashboardMetricsService $metricas): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole('admin') && ! $user->hasRole('rrhh') && ! $user->can('crearTrabajadorPropio', User::class)) {
            $this->redirectRoute('trabajador.papeletas.index');
        }
    }

    public function render(DashboardMetricsService $metricas): View
    {
        /** @var User $user */
        $user = Auth::user();

        $esAdmin = $user->hasRole('admin');
        $esRrhh = $user->hasRole('rrhh');
        $esJefe = $user->can('crearTrabajadorPropio', User::class);

        return view('livewire.dashboard', [
            'esAdmin' => $esAdmin,
            'esRrhh' => $esRrhh,
            'esJefe' => $esJefe,
            'admin' => $esAdmin ? $metricas->paraAdmin() : null,
            'rrhh' => $esRrhh ? $metricas->paraRrhh() : null,
            'jefe' => $esJefe ? $metricas->paraJefe($user) : null,
        ]);
    }
}
