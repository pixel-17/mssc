<?php

namespace App\States\Papeleta;

/**
 * La salida terminó (por retorno o por abandono) y el motivo exige
 * justificación: el descuento queda en suspenso hasta que se presente y
 * se revise, o venza el plazo. No es terminal.
 */
class EnJustificacion extends PapeletaState
{
    /** Valor persistido en `papeletas.estado`: desacopla la BD del nombre/namespace de la clase. */
    public static ?string $name = 'en_justificacion';

    public function esTerminal(): bool
    {
        return false;
    }
}
