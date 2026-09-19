<?php

namespace App\States\Papeleta;

class PendienteJefe extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'pendiente_jefe';

    public function esTerminal(): bool
    {
        return false;
    }
}
