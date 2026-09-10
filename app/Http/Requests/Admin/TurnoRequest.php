<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Desde que el régimen 276 (ordinario) pasó a validarse contra el
 * horario único global (HorarioOrdinarioService) en vez de una fila
 * diaria por trabajador, esta pantalla de Turnos es informativa para
 * TODOS los regímenes: solo el 728 (rotativo) la usa en la práctica,
 * y ninguno de los dos bloquea la creación de papeleta por esto.
 */
class TurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // protegido por middleware 'role:admin' en la ruta
    }

    public function rules(): array
    {
        $actual = $this->route('turno');

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
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
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
