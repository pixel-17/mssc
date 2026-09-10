<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 5 / Salud: el trabajador sube el archivo. Subir NO cierra el
 * caso (eso lo decide un humano después vía RevisarSustentoAction) —
 * aquí solo se valida la forma del archivo.
 */
class SustentoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('subirSustento', $this->route('sustento')->papeleta);
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:5120'],
        ];
    }
}
