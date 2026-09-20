<?php

namespace App\Http\Requests\Papeleta;

/**
 * Observación del jefe: el comentario de siempre más la decisión de si
 * el trabajador, además de responder por escrito, debe adjuntar un archivo.
 */
class ObservarJefeRequest extends ComentarioRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'requiere_adjunto' => ['nullable', 'boolean'],
        ];
    }
}
