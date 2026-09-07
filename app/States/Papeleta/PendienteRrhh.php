<?php

namespace App\States\Papeleta;

class PendienteRrhh extends PapeletaState
{
    public function esTerminal(): bool
    {
        return false;
    }
}
