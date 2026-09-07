<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protegido por middleware 'role:admin' en la ruta
    }

    public function rules(): array
    {
        return [
            'valor' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
