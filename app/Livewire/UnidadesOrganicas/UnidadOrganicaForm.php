<?php

namespace App\Livewire\UnidadesOrganicas;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reemplaza a UnidadOrganicaResource::form() de Filament. El jefe_id
 * de esta unidad es el Jefe Inmediato de sus miembros; el jefe_id de
 * la unidad padre es el Jefe de Área (ver UnidadOrganica::jefeInmediato()
 * / jefeArea()). `tipo` es solo decorativo, nunca condiciona el
 * escalamiento de papeletas.
 */
#[Layout('layouts.app')]
class UnidadOrganicaForm extends Component
{
    public ?UnidadOrganica $unidad = null;

    public string $nombre = '';

    public ?string $tipo = null;

    public ?int $parentId = null;

    public ?int $jefeId = null;

    public bool $activo = true;

    public const TIPOS = [
        'alta_direccion' => 'Alta dirección',
        'consultivo' => 'Consultivo',
        'control' => 'Control',
        'apoyo' => 'Apoyo',
        'apoyo_alcaldia' => 'Apoyo a alcaldía',
        'asesoramiento' => 'Asesoramiento',
        'linea_2do_nivel' => 'Línea - 2do nivel',
        'linea_3er_nivel' => 'Línea - 3er nivel',
    ];

    public function mount(?UnidadOrganica $unidad = null): void
    {
        if ($unidad?->exists) {
            $this->unidad = $unidad;
            $this->nombre = $unidad->nombre;
            $this->tipo = $unidad->tipo;
            $this->parentId = $unidad->parent_id;
            $this->jefeId = $unidad->jefe_id;
            $this->activo = $unidad->activo;
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(self::TIPOS))],
            'parentId' => ['nullable', 'exists:unidad_organicas,id'],
            'jefeId' => ['nullable', 'exists:users,id'],
            'activo' => ['boolean'],
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        // No puede ser su propio padre ni el de ninguno de sus
        // descendientes, o el árbol se vuelve cíclico.
        if ($this->unidad && $datos['parentId']) {
            $prohibidos = [$this->unidad->id, ...$this->unidad->descendantIds()];

            if (in_array((int) $datos['parentId'], $prohibidos, true)) {
                $this->addError('parentId', 'Esa unidad no puede ser su propio padre ni el de un descendiente suyo.');

                return;
            }
        }

        $atributos = [
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'parent_id' => $datos['parentId'],
            'jefe_id' => $datos['jefeId'],
            'activo' => $datos['activo'],
        ];

        $this->unidad
            ? $this->unidad->update($atributos)
            : UnidadOrganica::create($atributos);

        session()->flash('mensaje', $this->unidad ? 'Unidad orgánica actualizada.' : 'Unidad orgánica creada.');

        $this->redirectRoute('unidades-organicas.index', navigate: false);
    }

    public function render(): View
    {
        $padresDisponibles = UnidadOrganica::orderBy('nombre')
            ->when($this->unidad, fn ($query) => $query
                ->whereKeyNot($this->unidad->id)
                ->whereNotIn('id', $this->unidad->descendantIds()))
            ->pluck('nombre', 'id');

        return view('livewire.unidades-organicas.unidad-organica-form', [
            'padresDisponibles' => $padresDisponibles,
            'jefesDisponibles' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
