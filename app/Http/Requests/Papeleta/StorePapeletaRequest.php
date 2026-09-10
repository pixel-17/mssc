<?php

namespace App\Http\Requests\Papeleta;

use App\Models\Motivo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Paso 1: creación. La validación de "justificación obligatoria si
 * adjunto = obligatorio" también la hace CrearPapeletaAction, pero se
 * repite aquí para dar el mensaje de error en el formulario antes de
 * llegar a la capa de negocio.
 */
class StorePapeletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crear', \App\Models\Papeleta::class);
    }

    public function rules(): array
    {
        return [
            'motivo_id' => ['required', 'integer', 'exists:motivos,id'],
            'justificacion' => ['nullable', 'string', 'max:2000'],
            'adjunto_inicial_path' => ['nullable', 'file', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $motivo = Motivo::find($this->input('motivo_id'));

            if ($motivo && $motivo->adjunto === 'obligatorio' && ! $this->filled('justificacion')) {
                $validator->errors()->add('justificacion', "La justificación es obligatoria para el motivo {$motivo->nombre}.");
            }
        });
    }
}
