<?php

namespace App\States\Papeleta;

class Rechazada extends PapeletaState
{
    public function esTerminal(): bool
    {
        return true;
    }
}
