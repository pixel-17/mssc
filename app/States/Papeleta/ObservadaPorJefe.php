<?php

namespace App\States\Papeleta;

class ObservadaPorJefe extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'observada_por_jefe';

    public function esTerminal(): bool
    {
        return false;
    }
}
