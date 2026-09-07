<?php

namespace App\States\Papeleta;

class Cerrada extends PapeletaState
{
    public function esTerminal(): bool
    {
        return true;
    }
}
