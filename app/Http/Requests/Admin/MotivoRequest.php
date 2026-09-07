<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MotivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protegido por middleware 'role:admin' en la ruta
    }

    public function rules(): array
    {
        $actual = $this->route('motivo');

        return [
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('motivos', 'codigo')->ignore($actual?->id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'adjunto' => ['required', Rule::in(['no', 'opcional', 'flexible', 'obligatorio'])],
            'suma_descuento' => ['boolean'],
            'permite_bypass_aprobacion' => ['boolean'],
            'permite_cierre_sin_retorno' => ['boolean'],
            'requiere_sustento_en_retorno' => ['boolean'],
            'participa_regla_exclusividad' => ['boolean'],
            'activo' => ['boolean'],
        ];
    }
}
