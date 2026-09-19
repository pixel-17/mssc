<?php

namespace App\States\Papeleta;

class Cerrada extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'cerrada';

    public function esTerminal(): bool
    {
        return true;
    }
}
