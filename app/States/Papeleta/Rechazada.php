<?php

namespace App\States\Papeleta;

class Rechazada extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'rechazada';

    public function esTerminal(): bool
    {
        return true;
    }
}
