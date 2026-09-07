<?php

namespace App\States\Papeleta;

class PendienteJefe extends PapeletaState
{
    public function esTerminal(): bool
    {
        return false;
    }
}
