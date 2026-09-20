<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El trabajador responde a una observación del jefe: respuesta escrita
 * siempre obligatoria; adjunto obligatorio solo si el jefe lo exigió al
 * observar (observacion_requiere_adjunto), opcional en otro caso. Que la
 * papeleta esté realmente en OBSERVADA_POR_JEFE lo valida
 * SubsanarObservacionAction (con mensaje claro), no este Request.
 */
class SubsanarObservacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $papeleta = $this->route('papeleta');

        return $papeleta && (int) $papeleta->trabajador_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        $exigeAdjunto = (bool) $this->route('papeleta')?->observacion_requiere_adjunto;

        return [
            'respuesta' => ['required', 'string', 'min:5', 'max:2000'],
            'archivo' => [
                Rule::requiredIf($exigeAdjunto),
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ];
    }
}
