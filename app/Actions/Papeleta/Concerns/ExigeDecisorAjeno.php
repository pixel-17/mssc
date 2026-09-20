<?php

namespace App\Actions\Papeleta\Concerns;

use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;

/**
 * Regla transversal: nadie decide, revisa ni cierra su PROPIA papeleta.
 *
 * PapeletaPolicy ya la aplica en la capa HTTP (esPropia); esto la
 * repite en la capa de dominio para que ninguna ruta nueva, comando o
 * componente Livewire que llame a la Action directamente pueda saltarla.
 * Aplica a quien decide, no al trabajador dueño (cancelar, marcar su
 * retorno o subsanar son acciones propias y siguen permitidas).
 */
trait ExigeDecisorAjeno
{
    protected function exigirDecisorAjeno(Papeleta $papeleta, User $decisor): void
    {
        if ((int) $papeleta->trabajador_id === (int) $decisor->id) {
            throw new PapeletaException('No puedes decidir, revisar ni cerrar tu propia papeleta.');
        }
    }
}
