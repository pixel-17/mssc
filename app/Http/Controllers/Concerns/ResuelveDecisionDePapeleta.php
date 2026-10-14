<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PapeletaException;
use Illuminate\Http\RedirectResponse;

/**
 * El patrón "ejecuta la Action, si falla con PapeletaException vuelve con el
 * error, si no vuelve con el mensaje de éxito" se repetía igual en 11
 * métodos entre Jefe\DecisionController y Rrhh\DecisionController.
 *
 * Solo cubre ese caso simple: un método que termina con back()->with(...)
 * y no necesita limpiar un archivo subido ni usar withInput() en el error
 * (responderPosthoc y corregir, por ejemplo, se quedan con su propio
 * try/catch porque sí lo necesitan).
 */
trait ResuelveDecisionDePapeleta
{
    protected function resolverDecision(callable $accion, string $tipoMensaje, string $mensaje): RedirectResponse
    {
        try {
            $accion();
        } catch (PapeletaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($tipoMensaje, $mensaje);
    }
}
