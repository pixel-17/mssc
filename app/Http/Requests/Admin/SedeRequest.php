<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SedeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protegido por middleware 'role:admin' en la ruta
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'radio_metros' => ['required', 'integer', 'min:10', 'max:5000'],
            'activo' => ['boolean'],
        ];
    }
}
