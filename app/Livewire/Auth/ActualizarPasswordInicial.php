<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla que ve un usuario cuya contraseña inicial fue su DNI (ver
 * CrearUsuarioAction / UsuarioAdminForm) la primera vez que entra.
 * RedirigirSiDebeActualizarPassword es quien lo trae hasta aquí.
 *
 * Es opcional a propósito: "actualizar" y "omitir" hacen lo mismo en
 * cuanto a la bandera (la apagan), la única diferencia es si además
 * cambia la contraseña. Una vez apagada, el middleware no vuelve a
 * traerlo aquí.
 */
#[Layout('layouts.app')]
class ActualizarPasswordInicial extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function actualizar(): void
    {
        $datos = $this->validate();

        Auth::user()->update([
            'password' => Hash::make($datos['password']),
            'debe_actualizar_password' => false,
        ]);

        session()->flash('mensaje', 'Contraseña actualizada.');

        $this->redirectRoute('dashboard', navigate: false);
    }

    public function omitir(): void
    {
        Auth::user()->update(['debe_actualizar_password' => false]);

        $this->redirectRoute('dashboard', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.actualizar-password-inicial');
    }
}
