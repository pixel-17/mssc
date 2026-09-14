<?php

namespace App\Http\Controllers;

use App\Models\NotificacionSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    /**
     * Misma ruta para dos consumidores: el dropdown de campana (fetch con
     * Accept: application/json) sigue recibiendo JSON tal como antes; la
     * navegación normal del navegador (tab "Avisos" del bottom-nav) recibe
     * la página completa. Así se evita duplicar ruta y lógica de consulta.
     */
    public function index(Request $request): JsonResponse|View
    {
        if (! $request->wantsJson()) {
            return view('notificaciones.index', [
                'notificaciones' => $request->user()
                    ->notificaciones()
                    ->where('canal', 'SISTEMA')
                    ->latest()
                    ->paginate(20),
            ]);
        }

        $notificaciones = $request->user()
            ->notificaciones()
            ->where('canal', 'SISTEMA')
            ->latest()
            ->limit(15)
            ->get();

        return response()->json([
            'notificaciones' => $notificaciones,
            'no_leidas' => $request->user()->notificaciones()->where('canal', 'SISTEMA')->noLeidas()->count(),
        ]);
    }

    public function marcarLeida(NotificacionSistema $notificacion): JsonResponse
    {
        abort_unless($notificacion->user_id === request()->user()->id, 403);

        $notificacion->marcarLeida();

        return response()->json(['status' => 'ok']);
    }

    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $request->user()->notificaciones()->noLeidas()->update(['leida_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
