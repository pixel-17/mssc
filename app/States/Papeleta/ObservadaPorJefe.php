<?php

namespace App\States\Papeleta;

class ObservadaPorJefe extends PapeletaState
{
    public function esTerminal(): bool
    {
        return false;
    }
}
