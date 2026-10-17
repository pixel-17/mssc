<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama;

use App\Actions\Organigrama\EliminarUnidadOrganicaAction;
use App\Actions\Organigrama\GuardarUnidadOrganicaAction;
use App\Actions\Organigrama\JefaturaUnidadService;
use App\Actions\Organigrama\ReglasOrganigrama;
use App\Livewire\Concerns\ManejaExcepcionesDeUsuario;
use App\Livewire\Concerns\RequiereAdmin;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * CRUD de unidades orgánicas dentro del organigrama. Se abre con el evento
 * `org-unidad-abrir` ({id} para editar, {parentId} para crear una
 * sub-unidad, vacío para crear una raíz) y avisa con `org-actualizado`
 * para que el árbol se vuelva a pintar.
 *
 * - admin: editar, crear (sub-unidad o raíz) y eliminar, sin restricción.
 * - Jefe de Área: SOLO puede crear una sub-unidad nueva dentro de su propia
 *   área ($id === null, $parentId dentro de su área). No puede editar una
 *   unidad existente (eso incluye cambiar jefatura, que ya tiene su propio
 *   flujo en la ficha de persona) ni eliminar, ni crear una unidad raíz.
 *   El detalle fino (jefe asignado perteneciente a su área, etc.) lo
 *   vuelve a validar GuardarUnidadOrganicaAction.
 *
 * La lógica vive en GuardarUnidadOrganicaAction / EliminarUnidadOrganicaAction.
 */
class UnidadModal extends Component
{
    use ManejaExcepcionesDeUsuario;
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

    public ?string $error = null;

    #[On('org-unidad-abrir')]
    public function abrir(?int $id = null, ?int $parentId = null): void
    {
        $this->autorizarAccion($id, $parentId);
        $this->reset(['nombre', 'tipo', 'parentId', 'jefeId', 'error', 'pasarJefe']);
        $this->activo = true;
        $this->unidadId = null;
        $this->resetValidation();

        if ($id !== null) {
            $unidad = UnidadOrganica::findOrFail($id);
            $this->unidadId = $unidad->id;
            $this->nombre = $unidad->nombre;
            $this->tipo = $unidad->tipo;
            $this->parentId = $unidad->parent_id;
            $this->jefeId = $unidad->jefe_id;
            $this->activo = (bool) $unidad->activo;
        } else {
            $this->parentId = $parentId;
        }

        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    /**
     * admin: siempre. Jefe de Área: solo para CREAR ($id === null) una
     * sub-unidad dentro de su propia área ($parentId obligatorio y dentro
     * de su área). Nunca para editar una unidad existente ni para crear
     * una unidad raíz (sin $parentId).
     */
    protected function autorizarAccion(?int $id, ?int $parentId): User
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor !== null, 403);

        if ($actor->hasRole('admin')) {
            return $actor;
        }

        abort_unless(
            $id === null
                && $parentId !== null
                && app(ReglasOrganigrama::class)->esJefeDeAreaDe($actor, $parentId),
            403,
        );

        return $actor;
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
        ];
    }

    public function guardar(GuardarUnidadOrganicaAction $guardar): void
    {
        $this->autorizarAccion($this->unidadId, $this->parentId);
        $this->error = null;
        $datos = $this->validate();

        $unidad = $this->unidadId ? UnidadOrganica::find($this->unidadId) : null;

        if ($this->unidadId && ! $unidad) {
            $this->error = 'La unidad ya no existe.';

            return;
        }

        if (! $this->intentar(
            fn () => $guardar->ejecutar(auth()->user(), $unidad, [
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'] ?: null,
                'parent_id' => $datos['parentId'],
                'jefe_id' => $datos['jefeId'],
                'activo' => $datos['activo'],
            ], (bool) $datos['pasarJefe']),
            fn ($e) => $this->error = $e->getMessage(),
        )) {
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

        if (! $this->intentar(
            fn () => $eliminar->ejecutar(auth()->user(), $unidad),
            fn ($e) => $this->error = $e->getMessage(),
        )) {
            return;
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: 'Unidad eliminada.');
    }

    public function render(): View
    {
        if (! $this->abierto) {
            return view('livewire.organigrama.unidad-modal', ['padres' => [], 'jefes' => [], 'resumen' => null, 'contexto' => null, 'vista' => null, 'esAdmin' => false]);
        }

        /** @var User $actor */
        $actor = auth()->user();
        $esAdmin = $actor->hasRole('admin');

        $unidad = $this->unidadId ? UnidadOrganica::find($this->unidadId) : null;

        // Jefe de Área creando: solo puede depender de la unidad sobre la que
        // pulsó «Agregar sub-unidad» — el campo se bloquea en la vista, pero
        // igual se limita aquí la opción disponible.
        $padres = $esAdmin
            ? UnidadOrganica::orderBy('nombre')
                ->when($unidad, fn ($q) => $q->whereKeyNot($unidad->id)->whereNotIn('id', $unidad->descendantIds()))
                ->get(['id', 'nombre'])
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->nombre, 'hint' => null])
                ->all()
            : UnidadOrganica::whereKey($this->parentId)->get(['id', 'nombre'])
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->nombre, 'hint' => null])
                ->all();

        $jefesQuery = User::with('unidadOrganica:id,nombre')
            ->where('activo', true)
            ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'admin'))
            ->orderBy('name')->orderBy('apellido');

        if (! $esAdmin) {
            // Jefe de Área: el jefe que asigne a la sub-unidad nueva debe ser de su propia área.
            $reglas = app(ReglasOrganigrama::class);
            $padreActual = $this->parentId ? UnidadOrganica::find($this->parentId) : null;
            $areaId = $padreActual ? $reglas->areaDe($actor, $padreActual) : null;
            $idsDelArea = $areaId
                ? collect([$areaId])->merge(UnidadOrganica::find($areaId)?->descendantIds() ?? [])
                : collect();

            $jefesQuery->whereIn('unidad_organica_id', $idsDelArea);
        }

        $jefes = $jefesQuery->get()
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
            'esAdmin' => $esAdmin,
        ]);
    }
}
