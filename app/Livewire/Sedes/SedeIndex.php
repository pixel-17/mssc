<?php

namespace App\Livewire\Sedes;

use App\Livewire\Concerns\RequiereAdmin;
use App\Models\Sede;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Listado del catálogo de Sedes en Blade + Livewire puro. Ver App\Livewire\Sedes\SedeForm
 * para crear/editar.
 */
#[Layout('layouts.app')]
#[Title('Sedes')]
class SedeIndex extends Component
{
    use RequiereAdmin;

    public string $buscar = '';

    public function eliminar(Sede $sede): void
    {
        $this->autorizarAdmin();

        // users.sede_id usa ->nullOnDelete() (ver migración create_users_table):
        // el DELETE de la sede NUNCA falla por FK aunque tenga trabajadores
        // asignados, la BD simplemente les pone sede_id = null en silencio.
        // Por eso no basta con esperar la QueryException de más abajo (esa
        // solo cubre papeletas, cuya FK sí es RESTRICT): hay que cortar
        // explícitamente aquí si hay usuarios, antes de que eso pase.
        if ($sede->usuarios()->exists()) {
            $sede->forceFill(['activo' => false])->save();
            session()->now('mensaje', 'Sede desactivada: tiene usuarios asociados y no puede borrarse.');

            return;
        }

        try {
            $sede->delete();
            session()->now('mensaje', 'Sede eliminada.');
        } catch (QueryException $e) {
            // FK de papeletas (RESTRICT, ver migración create_papeletas_table):
            // no se pierde historial, se desactiva.
            if (! str_starts_with((string) $e->getCode(), '23')) {
                throw $e;
            }

            $sede->forceFill(['activo' => false])->save();
            session()->now('mensaje', 'Sede desactivada: tiene papeletas asociadas y no puede borrarse.');
        }
    }

    public function render(): View
    {
        return view('livewire.sedes.sede-index', [
            'sedes' => Sede::withCount('usuarios')->when(trim($this->buscar) !== '', fn ($q) => $q->where(fn ($w) => $w->where('nombre', 'like', '%'.trim($this->buscar).'%')->orWhere('direccion', 'like', '%'.trim($this->buscar).'%')))->orderBy('nombre')->get(),
        ]);
    }
}