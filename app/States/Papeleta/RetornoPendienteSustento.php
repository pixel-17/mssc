<?php

namespace App\States\Papeleta;

class RetornoPendienteSustento extends PapeletaState
{
    public function esTerminal(): bool
    {
        return false;
    }
}
