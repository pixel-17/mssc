<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el usuario autenticado tiene debe_actualizar_password = true
 * (se marca al crearlo con contraseña = DNI, ver CrearUsuarioAction y
 * UsuarioAdminForm), lo manda a la pantalla de actualizar contraseña
 * en su próxima navegación de página completa. Es opcional: desde ahí
 * puede actualizarla u omitirla — cualquiera de las dos apaga la
 * bandera (ver ActualizarPasswordInicial), así que esto solo se
 * dispara una vez, en su primer ingreso real.
 *
 * Solo actúa sobre GET de página completa: las peticiones internas de
 * Livewire (POST a /livewire/update) no pasan por aquí, para no
 * interrumpir la interactividad de otros componentes montados antes
 * de que el usuario llegue a esta pantalla.
 */
class RedirigirSiDebeActualizarPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if (
            $usuario?->debe_actualizar_password
            && $request->isMethod('get')
            && ! $request->routeIs('password.actualizar-inicial')
            && ! $request->routeIs('logout')
        ) {
            return redirect()->route('password.actualizar-inicial');
        }

        return $next($request);
    }
}
