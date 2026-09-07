<?php

namespace App\States\Papeleta;

class AutorizadaYCorriendo extends PapeletaState
{
    public function esTerminal(): bool
    {
        return false;
    }
}
