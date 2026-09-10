<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 5, excepción de falla de conectividad: lo marca el jefe
 * inmediato, sin foto/GPS, con justificación obligatoria.
 */
class RetornoManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marcarRetornoManual', $this->route('papeleta'));
    }

    public function rules(): array
    {
        return [
            'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
