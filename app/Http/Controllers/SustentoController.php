<?php

namespace App\Http\Controllers;

use App\Actions\Papeleta\RevisarSustentoAction;
use App\Exceptions\PapeletaException;
use App\Http\Requests\Papeleta\ComentarioRequest;
use App\Http\Requests\Papeleta\SustentoUploadRequest;
use App\Models\Sustento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Paso 5 (motivo Salud) / Paso 8: subir un archivo nunca cierra el
 * caso por sí solo — necesita el visto bueno humano que da
 * RevisarSustentoAction. Este controller separa esas dos cosas:
 * store() es el trabajador subiendo evidencia, aprobar()/observar()
 * es el jefe o RRHH decidiendo sobre ella.
 */
class SustentoController extends Controller
{
    public function store(SustentoUploadRequest $request, Sustento $sustento): RedirectResponse
    {
        if ($sustento->estado === 'aprobado') {
            return back()->with('error', 'Este sustento ya fue aprobado.');
        }

        $sustento->update([
            'archivo_path' => $request->file('archivo')->store('papeletas/sustentos', 'public'),
            'presentado_at' => now(),
            'estado' => 'presentado',
        ]);

        return redirect()
            ->route('papeletas.show', $sustento->papeleta_id)
            ->with('status', 'Archivo de sustento presentado. Queda pendiente de revisión.');
    }

    public function aprobar(Request $request, Sustento $sustento, RevisarSustentoAction $action): RedirectResponse
    {
        $this->authorize('revisarSustento', $sustento->papeleta);

        return $this->intentar($sustento, fn () => $action->aprobar($sustento, $request->user()), 'Sustento aprobado. Papeleta cerrada.');
    }

    public function observar(ComentarioRequest $request, Sustento $sustento, RevisarSustentoAction $action): RedirectResponse
    {
        $this->authorize('revisarSustento', $sustento->papeleta);

        return $this->intentar(
            $sustento,
            fn () => $action->observar($sustento, $request->user(), $request->input('comentario')),
            'Sustento observado. El trabajador deberá presentar uno nuevo.'
        );
    }

    private function intentar(Sustento $sustento, \Closure $callback, string $mensajeExito): RedirectResponse
    {
        try {
            $callback();
        } catch (PapeletaException $e) {
            return redirect()->route('papeletas.show', $sustento->papeleta_id)->with('error', $e->getMessage());
        }

        return redirect()->route('papeletas.show', $sustento->papeleta_id)->with('status', $mensajeExito);
    }
}
