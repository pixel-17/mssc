<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El jefe que autorizó responde la observación post-hoc de RRHH:
 * respuesta escrita obligatoria y adjunto (sustento) opcional. Que la
 * revisión esté realmente 'observada' y que quien responde sea el jefe
 * que autorizó lo validan la Policy y ResponderPosthocAction (con
 * mensaje claro), no este Request.
 */
class ResponderPosthocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'respuesta' => ['required', 'string', 'min:5', 'max:2000'],
            'archivo' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
