<?php

namespace App\States\Papeleta;

class ObservadaPorRrhh extends PapeletaState
{
    public function esTerminal(): bool
    {
        return false;
    }
}
