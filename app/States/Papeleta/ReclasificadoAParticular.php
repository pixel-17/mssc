<?php

namespace App\States\Papeleta;

class ReclasificadoAParticular extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'reclasificado_a_particular';

    public function esTerminal(): bool
    {
        return true;
    }
}
