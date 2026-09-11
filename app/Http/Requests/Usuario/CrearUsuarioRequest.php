<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * La restricción de QUIÉN puede crear va en UsuarioController (policy),
 * no aquí — authorize() solo exige estar autenticado. Esta clase solo
 * valida la FORMA de los datos, incluyendo que la unidad elegida (si
 * aplica) caiga dentro del área del creador.
 */
class CrearUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadesDisponibles = $this->unidadesDisponibles();
        $esJefeDeArea = $unidadesDisponibles->isNotEmpty();

        return [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'size:8', 'unique:users,dni'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'regimen' => ['required', Rule::in(['276', '728'])],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'tipo' => $esJefeDeArea
                ? ['required', Rule::in(['trabajador', 'jefe_inmediato'])]
                : ['nullable'],
            'unidad_organica_id' => $esJefeDeArea
                ? ['required', Rule::in($unidadesDisponibles->all())]
                : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.size' => 'El DNI debe tener 8 dígitos.',
            'dni.unique' => 'Ya existe un usuario con ese DNI.',
            'unidad_organica_id.in' => 'Esa unidad no pertenece a tu área.',
        ];
    }

    /**
     * IDs de unidades donde el usuario autenticado puede crear como
     * Jefe de Área: las que encabeza + todas sus sub-unidades. Vacío
     * si no encabeza ninguna (es decir, si solo es Jefe Inmediato).
     */
    public function unidadesDisponibles(): Collection
    {
        $encabezadas = $this->user()->unidadesQueEncabeza;

        $ids = collect();
        foreach ($encabezadas as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
