<?php

namespace App\Http\Requests\Papeleta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 5, motivo Salud: el trabajador sube el archivo de sustento
 * dentro de las 48h hábiles. Subir el archivo NO cierra el caso por
 * sí solo (ver RevisarSustentoAction) — solo deja el sustento en
 * estado "presentado", a la espera de visto bueno humano.
 */
class SubirSustentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sustento = $this->route('sustento');

        return $sustento && $sustento->papeleta->trabajador_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:10240'],
        ];
    }
}
