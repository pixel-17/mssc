<?php

namespace App\Http\Requests\Usuario;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * La restricción de QUIÉN puede crear va en UsuarioController (policy),
 * no aquí — authorize() solo exige estar autenticado. Esta clase solo
 * valida la FORMA de los datos, incluyendo que la unidad elegida (si
 * aplica) caiga dentro del área del creador.
 *
 * Régimen: cada jefe inmediato tiene trabajadores de su mismo régimen
 * (ya no se permiten mezclas) — ver withValidator() y
 * regimenEsperado().
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
            // 728 SIEMPRE necesita su turno vigente para crear una
            // papeleta (ver CrearPapeletaAction): se exige en el mismo
            // paso del alta para que no quede ningún 728 sin horario
            // desde el día uno (el generador automático mensual solo
            // continúa una configuración que ya existe, nunca crea la
            // primera).
            'turno' => [
                Rule::requiredIf($this->input('regimen') === '728'),
                Rule::in(['MANANA', 'TARDE', 'NOCHE']),
            ],
            'fecha_ancla' => [Rule::requiredIf($this->input('regimen') === '728'), 'date'],
            'dias_trabajo' => ['nullable', 'integer', 'min:1', 'max:30'],
            'dias_descanso' => ['nullable', 'integer', 'min:1', 'max:30'],
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
            'turno.required' => 'Un trabajador 728 necesita su turno inicial: sin esto no podrá crear ninguna papeleta.',
            'fecha_ancla.required' => 'Indica desde cuándo empieza su próximo bloque de trabajo.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $regimenEsperado = $this->regimenEsperado();
            $regimenEnviado = $this->input('regimen');

            if ($regimenEsperado !== null && $regimenEnviado !== null && $regimenEnviado !== $regimenEsperado) {
                $validator->errors()->add(
                    'regimen',
                    "El régimen debe coincidir con el de su jefe inmediato (régimen {$regimenEsperado})."
                );
            }
        });
    }

    /**
     * Régimen que debe tener el nuevo usuario para coincidir con su
     * jefe inmediato — cada jefe inmediato solo tiene trabajadores de
     * su propio régimen:
     *
     * - Jefe Inmediato creando su propio trabajador (! esJefeDeArea):
     *   el régimen del propio creador, porque ÉL es quien queda como
     *   jefe inmediato automático (unidad_organica_id se hereda).
     * - Jefe de Área dando de alta un trabajador (no un jefe_inmediato
     *   nuevo) en una unidad que YA tiene jefe asignado: el régimen de
     *   ese jefe (jefe_id de la unidad).
     * - Cualquier otro caso (jefe_inmediato nuevo, o unidad sin jefe
     *   todavía): no hay nada contra qué comparar, se deja pasar.
     */
    private function regimenEsperado(): ?string
    {
        $unidadesDisponibles = $this->unidadesDisponibles();
        $esJefeDeArea = $unidadesDisponibles->isNotEmpty();

        if (! $esJefeDeArea) {
            return $this->user()->regimen;
        }

        if ($this->input('tipo') !== 'trabajador') {
            return null;
        }

        $unidad = UnidadOrganica::find($this->input('unidad_organica_id'));

        return $unidad?->jefe?->regimen;
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
