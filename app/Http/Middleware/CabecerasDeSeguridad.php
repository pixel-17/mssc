<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas web.
 *
 * - X-Frame-Options + frame-ancestors: que ninguna otra página meta esta en
 *   un iframe (clickjacking sobre los botones de aprobar/rechazar).
 * - Permissions-Policy: la app usa cámara (foto de retorno) y GPS solo desde
 *   su propio origen; el resto de funciones sensibles quedan apagadas.
 * - Referrer-Policy: no filtra rutas internas a sitios externos (OSM, etc.).
 * - HSTS: solo en producción y solo sobre HTTPS.
 *
 * NO se define Content-Security-Policy completa a propósito: Livewire y
 * Alpine ejecutan expresiones en línea y una CSP estricta rompería la
 * interfaz; solo se fija `frame-ancestors`, que es segura.
 *
 * No pisa cabeceras que la respuesta ya trae.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $cabeceras = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ];

        if (app()->isProduction() && $request->isSecure()) {
            $cabeceras['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($cabeceras as $nombre => $valor) {
            if (! $respuesta->headers->has($nombre)) {
                $respuesta->headers->set($nombre, $valor);
            }
        }

        return $respuesta;
    }
}
