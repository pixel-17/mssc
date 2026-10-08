<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\Sustento;
use Carbon\Carbon;

/**
 * Abre la justificación pendiente de una papeleta cuya salida terminó, sea
 * por retorno o por abandono. El plazo es el del motivo (o el global) y
 * corre desde `$desde`: la hora del retorno, o el momento en que se cerró
 * por abandono.
 */
class AbrirJustificacion
{
    public function __construct(private CalculadorDiasHabiles $diasHabiles) {}

    public function para(Papeleta $papeleta, Carbon $desde): Sustento
    {
        return Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => $this->diasHabiles->agregarHorasHabiles(
                $desde->copy(),
                $papeleta->motivo->plazoJustificacionHorasHabiles(),
            ),
            'estado' => 'pendiente',
        ]);
    }
}
