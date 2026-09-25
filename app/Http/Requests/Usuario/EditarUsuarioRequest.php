<?php

namespace App\Http\Requests\Usuario;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Edición de un trabajador existente por Jefe de Área / Jefe
 * Inmediato (ver UsuarioController::update()). La autorización de
 * QUIÉN puede editar a QUIÉN vive en UserPolicy::editar(), no aquí.
 *
 * A propósito NO incluye dni, regimen, activo, turno ni sede_id/
 * unidad_organica_id para un Jefe Inmediato "puro":
 * - dni/regimen/activo: terreno de admin (UsuarioAdminForm).
 * - turno: tiene su propia pantalla (turnos.configuracion, ver
 *   EditarUsuarioAction) — no se valida ni se toca aquí.
 * - sede_id/unidad_organica_id: implícitos para el Jefe Inmediato
 *   puro; un Jefe de Área sí puede reubicar al trabajador entre las
 *   unidades de su propia área (mismo criterio que al crear, ver
 *   CrearUsuarioRequest::unidadesDisponibles()).
 */
class EditarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadesDisponibles = $this->unidadesDisponibles();
        $esJefeDeArea = $unidadesDisponibles->isNotEmpty();

        /** @var User $trabajador */
        $trabajador = $this->route('trabajador');

        return [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($trabajador->id),
            ],
            'sede_id' => $esJefeDeArea ? ['nullable', 'exists:sedes,id'] : ['nullable'],
            'unidad_organica_id' => $esJefeDeArea
                ? ['required', Rule::in($unidadesDisponibles->all())]
                : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Ese correo ya está en uso por otro usuario.',
            'unidad_organica_id.in' => 'Esa unidad no pertenece a tu área.',
        ];
    }

    /**
     * IDs de unidades donde el usuario autenticado puede reubicar al
     * trabajador como Jefe de Área: las que encabeza + sus
     * sub-unidades. Vacío si no encabeza ninguna (o sea, si edita como
     * Jefe Inmediato "puro").
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
