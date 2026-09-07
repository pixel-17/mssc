<?php

namespace App\States\Papeleta;

class ReclasificadoAParticular extends PapeletaState
{
    public function esTerminal(): bool
    {
        return true;
    }
}
