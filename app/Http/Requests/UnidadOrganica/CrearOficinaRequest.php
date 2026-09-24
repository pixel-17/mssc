<?php

namespace App\Http\Requests\UnidadOrganica;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Quién puede enviar esto se resuelve en el controller vía
 * UserPolicy::crearOficina (igual que CrearUsuarioRequest) — acá solo
 * se valida la FORMA: que el padre elegido esté en el subárbol del
 * usuario autenticado y que cada jefe tenga datos válidos.
 */
class CrearOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadesDisponibles = $this->unidadesDisponibles();
        $jefesEnviados = $this->input('jefes', []);
        $haySobreUnJefe = count($jefesEnviados) > 1;

        $reglas = [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(\App\Livewire\UnidadesOrganicas\UnidadOrganicaForm::TIPOS))],
            'parent_id' => ['required', Rule::in($unidadesDisponibles->all())],

            'jefes' => ['required', 'array', 'min:1', 'max:3'],
            'jefes.*.name' => ['required', 'string', 'max:255'],
            'jefes.*.apellido' => ['required', 'string', 'max:255'],
            'jefes.*.dni' => ['required', 'string', 'size:8', 'distinct'],
            'jefes.*.email' => ['required', 'string', 'email', 'max:255', 'distinct'],
            'jefes.*.regimen' => ['required', Rule::in(['276', '728'])],
            'jefes.*.dias_trabajo' => ['nullable', 'integer', 'min:1', 'max:30'],
            'jefes.*.dias_descanso' => ['nullable', 'integer', 'min:1', 'max:30'],
            'jefes.*.sede_id' => ['nullable', 'exists:sedes,id'],
        ];

        // 'turno' y 'fecha_ancla' solo son obligatorios para el jefe cuyo
        // PROPIO régimen es 728 (CrearUsuarioAction los exige para cargar
        // su ciclo de turno) — por eso se arman por índice y no con un
        // requiredIf genérico sobre 'jefes.*', que no distingue entre
        // jefes 276 y 728 dentro del mismo array.
        foreach ($jefesEnviados as $i => $jefe) {
            $es728 = ($jefe['regimen'] ?? null) === '728';

            $reglas["jefes.$i.turno"] = [
                Rule::requiredIf($es728 && $haySobreUnJefe),
                'nullable', Rule::in(['MANANA', 'TARDE', 'NOCHE']),
            ];
            $reglas["jefes.$i.fecha_ancla"] = [Rule::requiredIf($es728), 'nullable', 'date'];
        }

        return $reglas;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $jefes = $this->input('jefes', []);

            // Turnos distintos por jefe cuando hay más de uno (no tiene
            // sentido registrar dos jefes para el mismo turno de la misma
            // oficina — el segundo pisaría al primero en jefes_turno).
            if (count($jefes) > 1) {
                $turnos = array_filter(array_column($jefes, 'turno'));

                if (count($turnos) !== count(array_unique($turnos))) {
                    $validator->errors()->add('jefes', 'No puedes asignar el mismo turno a dos jefes distintos.');
                }
            }

            // DNI duplicado contra usuarios activos ya existentes (mismo
            // criterio que CrearUsuarioRequest, pero por cada jefe del array).
            foreach ($jefes as $i => $jefe) {
                if (User::where('dni', $jefe['dni'] ?? null)->where('activo', true)->exists()) {
                    $validator->errors()->add("jefes.$i.dni", 'Ya existe un usuario activo con ese DNI.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'parent_id.in' => 'Esa unidad padre no pertenece a tu área.',
            'jefes.max' => 'No puedes registrar más de un jefe por turno (máximo 3).',
            'jefes.*.dni.size' => 'El DNI debe tener 8 dígitos.',
            'jefes.*.dni.distinct' => 'Dos jefes de la misma oficina no pueden tener el mismo DNI.',
            'jefes.*.email.distinct' => 'Dos jefes de la misma oficina no pueden tener el mismo correo.',
            'jefes.*.turno.required' => 'Con más de un jefe en régimen 728, cada uno necesita su turno.',
            'jefes.*.fecha_ancla.required' => 'Indica desde cuándo empieza su próximo bloque de trabajo.',
        ];
    }

    /**
     * IDs de unidades donde el usuario autenticado puede crear una
     * oficina hija: las que encabeza + todas sus sub-unidades. Mismo
     * criterio que CrearUsuarioRequest::unidadesDisponibles(), pero acá
     * también incluye admin (todo el árbol) porque admin sigue pudiendo
     * usar este flujo si quiere, aunque ya no sea su única vía.
     */
    public function unidadesDisponibles(): Collection
    {
        if ($this->user()->hasRole('admin')) {
            return UnidadOrganica::pluck('id');
        }

        $encabezadas = $this->user()->unidadesQueEncabeza;

        $ids = collect();
        foreach ($encabezadas as $unidad) {
            $ids->push($unidad->id);
            $ids = $ids->merge($unidad->descendantIds());
        }

        return $ids->unique()->values();
    }
}
