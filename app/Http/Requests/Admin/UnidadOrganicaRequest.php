<?php

namespace App\Http\Requests\Admin;

use App\Models\UnidadOrganica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadOrganicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La restricción real va en el middleware 'role:admin' de las rutas.
        // Cuando exista el seeder de permisos granulares de Spatie, cambiar
        // esto por: return $this->user()->can('gestionar-organigrama');
        return true;
    }

    public function rules(): array
    {
        /** @var UnidadOrganica|null $actual */
        $actual = $this->route('unidad_organica') ?? $this->route('unidadOrganica');

        $idsProhibidos = $actual
            ? array_merge([$actual->id], $actual->descendantIds())
            : [];

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', Rule::in([
                'alta_direccion', 'consultivo', 'control', 'apoyo',
                'apoyo_alcaldia', 'asesoramiento', 'linea_2do_nivel', 'linea_3er_nivel',
            ])],
            'parent_id' => [
                'nullable',
                'exists:unidades_organicas,id',
                Rule::notIn($idsProhibidos),
            ],
            'jefe_id' => ['nullable', 'exists:users,id'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'No se puede asignar como padre a la misma unidad ni a una de sus propias sub-oficinas: crearía un ciclo en el organigrama.',
        ];
    }
}
