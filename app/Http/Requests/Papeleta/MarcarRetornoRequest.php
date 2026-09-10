<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 5, retorno normal del trabajador: foto + GPS + hora del
 * servidor simultáneos. La hora del servidor la fija el Action
 * (now()), nunca el cliente.
 */
class MarcarRetornoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marcarRetorno', $this->route('papeleta'));
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'max:5120'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
