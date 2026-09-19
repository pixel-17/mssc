<?php

namespace App\Http\Requests\Usuario;

use App\Models\User;
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

        // Un DNI que coincide con alguien ya desactivado no es una alta
        // nueva, es un reingreso (ver CrearUsuarioAction::ejecutar()): se
        // ignora esa fila propia tanto para el unique de dni (solo se
        // compara contra activos) como para el de email (por si
        // reingresa con el mismo correo que ya tenía).
        $existenteInactivo = User::where('dni', $this->input('dni'))
            ->where('activo', false)
            ->first();

        return [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => [
                'required', 'string', 'size:8',
                Rule::unique('users', 'dni')->where(fn ($q) => $q->where('activo', true)),
            ],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($existenteInactivo?->id),
            ],
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
            'dni.unique' => 'Ya existe un usuario activo con ese DNI.',
            'email.unique' => 'Ese correo ya está en uso por otro usuario.',
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
