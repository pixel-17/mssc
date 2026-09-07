<?php

namespace App\States\Papeleta;

class Cancelada extends PapeletaState
{
    public function esTerminal(): bool
    {
        return true;
    }
}
