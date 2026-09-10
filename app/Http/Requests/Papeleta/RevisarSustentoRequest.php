<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Visto bueno humano (jefe o RRHH) sobre un sustento ya presentado.
 * 'resultado' decide si RevisarSustentoAction::aprobar() u
 * observar(); comentario solo obligatorio al observar.
 */
class RevisarSustentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resultado' => ['required', 'in:aprobado,observado'],
            'comentario' => ['required_if:resultado,observado', 'nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
