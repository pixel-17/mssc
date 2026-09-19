<?php

namespace App\States\Papeleta;

class AutorizadaYCorriendo extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'autorizada_y_corriendo';

    public function esTerminal(): bool
    {
        return false;
    }
}
