<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla que ve un usuario cuya contraseña inicial fue su DNI (ver
 * CrearUsuarioAction / UsuarioAdminForm) la primera vez que entra.
 * RedirigirSiDebeActualizarPassword es quien lo trae hasta aquí.
 *
 * Es OBLIGATORIA: la contraseña inicial es el DNI (un dato semipúblico),
 * así que el usuario no puede seguir usando el sistema hasta definir una
 * propia y distinta de su DNI. Solo guardar una contraseña nueva apaga la
 * bandera; el middleware no vuelve a traerlo aquí después.
 */
#[Layout('layouts.app')]
#[Title('Actualiza tu contraseña')]
class ActualizarPasswordInicial extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        $dni = (string) Auth::user()?->dni;

        return [
            'password' => array_filter([
                'required', 'string', 'min:8', 'confirmed',
                $dni !== '' ? Rule::notIn([$dni]) : null,
            ]),
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'password.not_in' => 'La nueva contraseña no puede ser tu DNI.',
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

    public function render(): View
    {
        return view('livewire.auth.actualizar-password-inicial');
    }
}
