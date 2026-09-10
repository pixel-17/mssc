<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 1: la validación de "adjunto obligatorio según el motivo" y la
 * de ventana de turno/exclusividad vive a propósito en
 * CrearPapeletaAction (son reglas de negocio, no de forma), este
 * Request solo valida la forma del dato que llega del formulario.
 */
class CrearPapeletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crear', \App\Models\Papeleta::class);
    }

    public function rules(): array
    {
        return [
            'motivo_id' => ['required', 'integer', 'exists:motivos,id'],
            'justificacion' => ['nullable', 'string', 'max:2000'],
            'adjunto' => ['nullable', 'file', 'max:5120'],
        ];
    }
}
