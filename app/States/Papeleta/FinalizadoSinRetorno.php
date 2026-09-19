<?php

namespace App\States\Papeleta;

class FinalizadoSinRetorno extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'finalizado_sin_retorno';

    public function esTerminal(): bool
    {
        return true;
    }
}
