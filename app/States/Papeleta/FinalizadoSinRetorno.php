<?php

namespace App\States\Papeleta;

class FinalizadoSinRetorno extends PapeletaState
{
    public function esTerminal(): bool
    {
        return true;
    }
}
