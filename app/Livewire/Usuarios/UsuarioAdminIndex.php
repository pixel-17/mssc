<?php

namespace App\Livewire\Usuarios;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Listado de Usuarios en Blade + Livewire puro (reemplaza a
 * UserResource::table() de Filament). Gestión SOLO para admin — ver
 * App\Livewire\Usuarios\UsuarioAdminForm para crear/editar.
 *
 * No confundir con App\Http\Controllers\Usuario\UsuarioController
 * (rutas 'usuarios.*'), que es la vía de alta para Jefe de Área /
 * Jefe Inmediato con reglas de autorización propias (UserPolicy).
 * Este módulo vive en 'usuarios-admin.*' para no pisar esas rutas.
 */
#[Layout('layouts.app')]
class UsuarioAdminIndex extends Component
{
    use WithPagination;

    public string $buscar = '';

    public ?string $regimen = null;

    public ?int $rolId = null;

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingRegimen(): void
    {
        $this->resetPage();
    }

    public function updatingRolId(): void
    {
        $this->resetPage();
    }

    public function eliminar(User $usuario): void
    {
        $usuario->delete();

        session()->flash('mensaje', 'Usuario eliminado.');
    }

    public function render(): View
    {
        $usuarios = User::with(['unidadOrganica', 'roles'])
            ->when($this->buscar, fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->buscar}%")
                    ->orWhere('apellido', 'like', "%{$this->buscar}%")
                    ->orWhere('dni', 'like', "%{$this->buscar}%")
                    ->orWhere('email', 'like', "%{$this->buscar}%")))
            ->when($this->regimen, fn ($query) => $query->where('regimen', $this->regimen))
            ->when($this->rolId, fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('roles.id', $this->rolId)))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.usuarios.usuario-admin-index', [
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
