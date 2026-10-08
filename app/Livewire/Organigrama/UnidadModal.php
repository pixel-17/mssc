<?php

namespace App\Livewire\Organigrama;

use App\Actions\Organigrama\EliminarUnidadOrganicaAction;
use App\Actions\Organigrama\GuardarUnidadOrganicaAction;
use App\Actions\Organigrama\JefaturaUnidadService;
use App\Exceptions\UsuarioException;
use App\Livewire\Concerns\RequiereAdmin;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * CRUD de unidades orgánicas dentro del organigrama (solo admin). Se abre
 * con el evento `org-unidad-abrir` ({id} para editar, {parentId} para
 * crear una sub-unidad, vacío para crear una raíz) y avisa con
 * `org-actualizado` para que el árbol se vuelva a pintar.
 *
 * La lógica vive en GuardarUnidadOrganicaAction / EliminarUnidadOrganicaAction.
 */
class UnidadModal extends Component
{
    use RequiereAdmin;

    public bool $abierto = false;

    #[Locked]
    public ?int $unidadId = null;

    public string $nombre = '';

    public ?string $tipo = null;

    public ?int $parentId = null;

    public ?int $jefeId = null;

    public bool $activo = true;

    /** Al cambiar de jefe: pasarlo a esta unidad (su superior pasa a ser el de la unidad padre). */
    public bool $pasarJefe = true;

    /** @var list<int|null> */
    public array $jefesAdicionales = [];

    public ?string $error = null;

    #[On('org-unidad-abrir')]
    public function abrir(?int $id = null, ?int $parentId = null): void
    {
        $this->autorizarAdmin();
        $this->reset(['nombre', 'tipo', 'parentId', 'jefeId', 'jefesAdicionales', 'error', 'pasarJefe']);
        $this->activo = true;
        $this->unidadId = null;
        $this->resetValidation();

        if ($id !== null) {
            $unidad = UnidadOrganica::with('jefesTurno')->findOrFail($id);
            $this->unidadId = $unidad->id;
            $this->nombre = $unidad->nombre;
            $this->tipo = $unidad->tipo;
            $this->parentId = $unidad->parent_id;
            $this->jefeId = $unidad->jefe_id;
            $this->activo = (bool) $unidad->activo;
            $this->jefesAdicionales = $unidad->jefesTurno->pluck('jefe_id')->all();
        } else {
            $this->parentId = $parentId;
        }

        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function agregarJefeAdicional(): void
    {
        $this->jefesAdicionales[] = null;
    }

    public function quitarJefeAdicional(int $indice): void
    {
        unset($this->jefesAdicionales[$indice]);
        $this->jefesAdicionales = array_values($this->jefesAdicionales);
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(UnidadOrganicaForm::TIPOS))],
            'parentId' => ['nullable', 'exists:unidad_organicas,id'],
            'jefeId' => ['nullable', 'exists:users,id'],
            'activo' => ['boolean'],
            'pasarJefe' => ['boolean'],
            'jefesAdicionales.*' => ['nullable', 'exists:users,id'],
        ];
    }

    public function guardar(GuardarUnidadOrganicaAction $guardar): void
    {
        $this->autorizarAdmin();
        $this->error = null;
        $datos = $this->validate();

        $unidad = $this->unidadId ? UnidadOrganica::find($this->unidadId) : null;

        if ($this->unidadId && ! $unidad) {
            $this->error = 'La unidad ya no existe.';

            return;
        }

        try {
            $guardar->ejecutar(auth()->user(), $unidad, [
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'] ?: null,
                'parent_id' => $datos['parentId'],
                'jefe_id' => $datos['jefeId'],
                'activo' => $datos['activo'],
            ], $unidad ? ($datos['jefesAdicionales'] ?? []) : null, (bool) $datos['pasarJefe']);
        } catch (UsuarioException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: $unidad ? 'Unidad actualizada.' : 'Unidad creada.');
    }

    public function eliminar(EliminarUnidadOrganicaAction $eliminar): void
    {
        $this->autorizarAdmin();
        $this->error = null;

        $unidad = $this->unidadId ? UnidadOrganica::find($this->unidadId) : null;

        if (! $unidad) {
            $this->abierto = false;

            return;
        }

        try {
            $eliminar->ejecutar(auth()->user(), $unidad);
        } catch (UsuarioException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: 'Unidad eliminada.');
    }

    public function render(): View
    {
        if (! $this->abierto) {
            return view('livewire.organigrama.unidad-modal', ['padres' => [], 'jefes' => [], 'resumen' => null, 'contexto' => null, 'vista' => null]);
        }

        $unidad = $this->unidadId ? UnidadOrganica::find($this->unidadId) : null;

        $padres = UnidadOrganica::orderBy('nombre')
            ->when($unidad, fn ($q) => $q->whereKeyNot($unidad->id)->whereNotIn('id', $unidad->descendantIds()))
            ->get(['id', 'nombre'])
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->nombre, 'hint' => null])
            ->all();

        $jefes = User::with('unidadOrganica:id,nombre')
            ->where('activo', true)
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'admin'))
            ->orderBy('name')->orderBy('apellido')
            ->get()
            ->map(fn (User $j) => ['id' => $j->id, 'label' => $j->nombre_completo, 'hint' => $j->unidadOrganica?->nombre])
            ->all();

        return view('livewire.organigrama.unidad-modal', [
            // Qué cambia al guardar (jefe, padre, personas) y por qué se rechazaría, antes de pulsar Guardar.
            'vista' => app(JefaturaUnidadService::class)->previsualizar($unidad, $this->jefeId, $this->parentId, $this->pasarJefe),
            'padres' => $padres,
            'jefes' => $jefes,
            // Para avisar de antemano por qué no se puede eliminar.
            'resumen' => $unidad ? [
                'personas' => $unidad->miembros()->count(),
                'hijos' => $unidad->hijos()->count(),
            ] : null,
            // Subtítulo: dónde cuelga la unidad (o cuántas personas tiene).
            'contexto' => $unidad
                ? $unidad->padre?->nombre
                : ($this->parentId ? 'Dentro de '.UnidadOrganica::whereKey($this->parentId)->value('nombre') : 'Unidad raíz'),
        ]);
    }
}
