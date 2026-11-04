<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios\Concerns;

use Spatie\Permission\Models\Role;

/**
 * Helpers de rol compartidos por UsuarioForm y UsuarioAdminForm. Antes de
 * extraerlos, los cuatro métodos de aquí vivían duplicados palabra por
 * palabra en ambos componentes — el riesgo real de esa duplicación no era
 * estético: si mañana cambia la regla de "qué cuenta como solo admin" hay
 * que acordarse de tocar los dos archivos, y un olvido ahí es un hueco de
 * permisos silencioso, no un error visible.
 *
 * Requiere que la clase que use este trait tenga:
 *  - public array $rolesSeleccionados
 *  - public ?\App\Models\User $usuario
 *  - public string $regimen
 *  - public string $turno
 *
 * No se tocó admiteConfiguracionTurno() ni buscarCuentaExistente(): aunque
 * se llaman igual en ambos componentes, su lógica SÍ difiere a propósito
 * (UsuarioForm sirve tres alcances —admin/área/equipo—, UsuarioAdminForm
 * solo admin), así que fusionarlos habría mezclado reglas de negocio
 * distintas en vez de eliminar una duplicación real.
 */
trait TieneRolesDeUsuario
{
    /**
     * ¿Los roles elegidos son únicamente el de administrador? El admin no
     * marca papeletas ni tiene turno, así que el régimen es opcional para él.
     */
    protected function esSoloAdmin(): bool
    {
        $adminId = (int) Role::where('name', 'admin')->value('id');
        $roles = array_map('intval', $this->rolesSeleccionados);

        return $adminId > 0 && $roles !== [] && array_diff($roles, [$adminId]) === [];
    }

    /** ¿Entre los roles elegidos está el de administrador? Un admin no tiene unidad ni jefes. */
    protected function incluyeAdmin(): bool
    {
        $adminId = (int) Role::where('name', 'admin')->value('id');

        return $adminId > 0 && in_array($adminId, array_map('intval', $this->rolesSeleccionados), true);
    }

    /** ¿Se está cambiando el régimen de un usuario ya existente? */
    protected function cambioDeRegimen(): bool
    {
        return $this->usuario !== null
            && ($this->usuario->regimen ?? '') !== ''
            && $this->regimen !== (string) $this->usuario->regimen;
    }

    /**
     * Al elegir otro régimen, el turno cargado en el formulario (el del
     * régimen anterior, p. ej. DIA) deja de ser válido: se limpia para
     * que se elija uno del régimen nuevo.
     */
    public function updatedRegimen(): void
    {
        if ($this->cambioDeRegimen()) {
            $this->turno = '';
        }
    }
}
