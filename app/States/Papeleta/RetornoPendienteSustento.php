<?php

namespace App\States\Papeleta;

class RetornoPendienteSustento extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'retorno_pendiente_sustento';

    public function esTerminal(): bool
    {
        return false;
    }
}
