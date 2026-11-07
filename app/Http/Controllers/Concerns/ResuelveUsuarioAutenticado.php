<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Auth::user() es nullable para PHPStan porque el guard, en abstracto, puede no
 * tener a nadie autenticado. Todas las rutas que usan este trait están detrás
 * del middleware 'auth' (ver routes/web.php), así que en tiempo de ejecución
 * siempre hay un usuario; este helper solo resuelve ese tipo para el resto
 * del método, igual que el patrón con doc-block @var que ya se usa en los
 * Livewire de Reportes.
 */
trait ResuelveUsuarioAutenticado
{
    protected function usuarioAutenticado(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
