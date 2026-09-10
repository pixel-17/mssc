<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Un único campo de texto reutilizado en todo el flujo para lo que el
 * documento llama indistintamente "motivo de rechazo", "comentario de
 * observación" o "justificación" (rechazo de jefe/RRHH, observación de
 * jefe/RRHH, reconocimiento de observación de RRHH, retorno manual por
 * falla de conectividad, revisión post-hoc observada, abandono sobre
 * retorno pendiente, observación de sustento). La autorización real
 * (quién puede accionar sobre esta papeleta en este estado) la decide
 * cada método del controller vía Policy, no este Request.
 */
class ComentarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comentario' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
