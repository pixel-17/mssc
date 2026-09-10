<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Punto de entrada único tras login: admin va a su panel de Filament,
 * RRHH a su bandeja (única para toda la municipalidad), y todos los
 * demás (trabajador, sea o no también jefe de alguien) a la bandeja
 * de trabajador. El acceso a la bandeja de jefe es un enlace aparte
 * en el menú, no la pantalla de entrada — ver routes/web.php.
 */
class DashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return redirect('/admin');
        }

        if ($user->hasRole('rrhh')) {
            return redirect()->route('rrhh.papeletas.index');
        }

        return redirect()->route('trabajador.papeletas.index');
    }
}
