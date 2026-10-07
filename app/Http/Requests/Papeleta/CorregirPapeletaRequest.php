<?php

namespace App\Http\Requests\Papeleta;

use App\Actions\Papeleta\CorregirPapeletaRrhhAction;
use Illuminate\Foundation\Http\FormRequest;

/** Corrección de RRHH sobre una papeleta ya cerrada. La autorización real vive en la Policy. */
class CorregirPapeletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hora_retorno' => ['nullable', 'date'],
            'motivo_id' => ['nullable', 'integer', 'exists:motivos,id'],
            'comentario' => ['required', 'string', 'min:'.CorregirPapeletaRrhhAction::MIN_JUSTIFICACION, 'max:2000'],
        ];
    }
}
