<?php

declare(strict_types=1);

namespace App\Livewire\UnidadesOrganicas;

use App\Actions\Organigrama\GuardarUnidadOrganicaAction;
use App\Livewire\Concerns\ManejaExcepcionesDeUsuario;
use App\Livewire\Concerns\RequiereAdmin;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Formulario de Unidades Orgánicas. El jefe_id
 * de esta unidad es el Jefe Inmediato de sus miembros; el jefe_id de
 * la unidad padre es el Jefe de Área (ver UnidadOrganica::jefeInmediato()
 * / jefeArea()). `tipo` es solo decorativo, nunca condiciona el
 * escalamiento de papeletas. Cada unidad tiene UN solo jefe, de cualquier
 * régimen.
 */
#[Layout('layouts.app')]
#[Title('Unidad orgánica')]
class UnidadOrganicaForm extends Component
{
    use ManejaExcepcionesDeUsuario;
    use RequiereAdmin;

    #[Locked]
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

    public function guardar(GuardarUnidadOrganicaAction $guardar): void
    {
        $this->autorizarAdmin();

        $datos = $this->validate();

        $esNueva = $this->unidad === null;

        if (! $this->intentar(
            fn () => $guardar->ejecutar(auth()->user(), $this->unidad, [
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'parent_id' => $datos['parentId'],
                'jefe_id' => $datos['jefeId'],
                'activo' => $datos['activo'],
            ]),
            fn ($e) => $this->addError('parentId', $e->getMessage()),
        )) {
            return;
        }

        session()->flash('mensaje', $esNueva ? 'Unidad orgánica creada.' : 'Unidad orgánica actualizada.');

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
            'jefesDisponibles' => User::whereDoesntHave('roles', fn ($r) => $r->where('name', 'admin'))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
