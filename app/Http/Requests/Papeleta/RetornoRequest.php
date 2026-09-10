<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 5, evidencia normal: foto + GPS son obligatorios aquí a nivel de
 * formulario (la hora del servidor la fija el propio Action con now(),
 * nunca llega del cliente). La excepción de falla de conectividad usa
 * ComentarioRequest + la ruta de retorno manual, no este Request.
 */
class RetornoRequest extends FormRequest
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
