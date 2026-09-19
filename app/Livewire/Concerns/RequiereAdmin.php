<?php

namespace App\Livewire\Concerns;

/**
 * `role:admin` en la ruta solo protege la carga inicial del componente:
 * las llamadas posteriores a métodos (`wire:click="eliminar(...)"`,
 * `guardar`) viajan por /livewire/update y no re-evalúan ese middleware
 * (p. ej. un admin al que le quitan el rol con la pestaña abierta seguía
 * pudiendo borrar). Las acciones destructivas llaman a esto.
 */
trait RequiereAdmin
{
    protected function autorizarAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }
}
