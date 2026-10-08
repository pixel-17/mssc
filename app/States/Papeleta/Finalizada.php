<?php

namespace App\States\Papeleta;

/**
 * Terminal CON descuento: Particular (normal o reclasificada) y los
 * abandonos / Salud que no se justificaron. Cómo se llegó aquí lo dice
 * `papeletas.causa_finalizacion_sin_retorno`, no un estado distinto.
 */
class Finalizada extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'finalizada';

    public function esTerminal(): bool
    {
        return true;
    }
}
