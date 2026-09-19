<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Complementa a Fortify::authenticateUsing() (ver FortifyServiceProvider):
 * ese callback bloquea el login de un usuario ya desactivado, pero no
 * hace nada por alguien que YA tenía sesión abierta cuando RR. HH. lo
 * desactivó. Este middleware cierra esa sesión en la siguiente request.
 *
 * Va al final del grupo 'web', igual que RedirigirSiDebeActualizarPassword,
 * porque necesita que Auth ya esté resuelto.
 */
class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta ha sido desactivada.']);
        }

        return $next($request);
    }
}
