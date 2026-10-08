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
 *
 * Si el trabajador ya adjuntó su justificación al crear la papeleta
 * (`adjunto_inicial_path`), el sustento nace `presentado`: no tiene que
 * subir nada otra vez y solo queda el visto bueno de quien revisa. Si no
 * adjuntó nada, nace `pendiente` y puede presentarla hasta `fecha_limite`.
 */
class AbrirJustificacion
{
    public function __construct(private CalculadorDiasHabiles $diasHabiles) {}

    public function para(Papeleta $papeleta, Carbon $desde): Sustento
    {
        $yaAdjunto = filled($papeleta->adjunto_inicial_path);

        return Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => $this->diasHabiles->agregarHorasHabiles(
                $desde->copy(),
                $papeleta->motivo->plazoJustificacionHorasHabiles(),
            ),
            'archivo_path' => $yaAdjunto ? $papeleta->adjunto_inicial_path : null,
            'estado' => $yaAdjunto ? 'presentado' : 'pendiente',
            'presentado_at' => $yaAdjunto ? now() : null,
        ]);
    }
}
