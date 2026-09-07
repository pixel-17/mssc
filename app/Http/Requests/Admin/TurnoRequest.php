<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protegido por middleware 'role:admin' en la ruta
    }

    public function rules(): array
    {
        $actual = $this->route('turno');

        // CAS: hora_inicio/hora_fin son obligatorias (salvo descanso) porque
        // el sistema valida contra ellas para permitir crear papeleta.
        // 728: son opcionales, la fila es solo informativa.
        $usuario = User::find($this->input('user_id'));
        $esCas = $usuario?->regimen === 'CAS';

        return [
            'user_id' => ['required', 'exists:users,id'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'fecha' => [
                'required', 'date',
                Rule::unique('turnos', 'fecha')
                    ->where('user_id', $this->input('user_id'))
                    ->ignore($actual?->id),
            ],
            'es_descanso' => ['boolean'],
            'hora_inicio' => [
                $esCas ? 'required_if:es_descanso,false' : 'nullable',
                'nullable', 'date_format:H:i',
            ],
            'hora_fin' => [
                $esCas ? 'required_if:es_descanso,false' : 'nullable',
                'nullable', 'date_format:H:i', 'after:hora_inicio',
            ],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if ($this->boolean('es_descanso')) {
            $data['hora_inicio'] = null;
            $data['hora_fin'] = null;
        }

        return $data;
    }
}
