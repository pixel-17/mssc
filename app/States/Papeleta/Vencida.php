<?php

namespace App\States\Papeleta;

class Vencida extends PapeletaState
{
    public function esTerminal(): bool
    {
        return true;
    }
}
