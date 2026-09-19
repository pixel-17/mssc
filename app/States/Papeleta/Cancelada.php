<?php

namespace App\States\Papeleta;

class Cancelada extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'cancelada';

    public function esTerminal(): bool
    {
        return true;
    }
}
