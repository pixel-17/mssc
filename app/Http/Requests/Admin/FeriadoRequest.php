<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeriadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protegido por middleware 'role:admin' en la ruta
    }

    public function rules(): array
    {
        $actual = $this->route('feriado');

        return [
            'fecha' => [
                'required', 'date',
                Rule::unique('feriados', 'fecha')->ignore($actual?->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
