<?php

namespace App\Livewire\Usuarios;

use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Reemplaza a UserResource::form() de Filament. Gestión SOLO para
 * admin — es el fin de la pirámide (ver UserPolicy), puede crear
 * cualquier usuario en cualquier unidad y con cualquier rol, sin las
 * restricciones de área que sí aplican a Jefe de Área / Jefe
 * Inmediato (ver Usuario\UsuarioController, la vía que ellos usan,
 * nunca este formulario).
 *
 * jefe_inmediato_id / jefe_area_id NO se editan aquí: se recalculan
 * solos vía UserObserver en cuanto se guarda unidad_organica_id. Para
 * que alguien sea "Jefe Inmediato" de una unidad, asígnalo como
 * jefe_id de esa unidad desde UnidadOrganicaForm.
 *
 * Contraseña: al crear, siempre es el DNI (el campo $password no se
 * usa en ese caso, ver guardar()) y se marca debe_actualizar_password
 * para que RedirigirSiDebeActualizarPassword se lo pida en su primer
 * ingreso (opcional). Al editar, el campo sigue existiendo como
 * reseteo manual opcional; si el admin lo llena, también se marca
 * debe_actualizar_password para ese usuario.
 */
#[Layout('layouts.app')]
class UsuarioAdminForm extends Component
{
    public ?User $usuario = null;

    public string $name = '';

    public string $apellido = '';

    public string $dni = '';

    public string $email = '';

    public string $password = '';

    public string $regimen = '';

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    public array $rolesSeleccionados = [];

    public function mount(?User $usuario = null): void
    {
        if ($usuario?->exists) {
            $this->usuario = $usuario;
            $this->name = $usuario->name;
            $this->apellido = $usuario->apellido;
            $this->dni = $usuario->dni;
            $this->email = $usuario->email;
            $this->regimen = $usuario->regimen;
            $this->sedeId = $usuario->sede_id;
            $this->unidadOrganicaId = $usuario->unidad_organica_id;
            $this->rolesSeleccionados = $usuario->roles->pluck('id')->all();
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => [
                'required', 'digits:8',
                Rule::unique('users', 'dni')->ignore($this->usuario?->id),
            ],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->usuario?->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'regimen' => ['required', 'in:276,728'],
            'sedeId' => ['nullable', 'exists:sedes,id'],
            'unidadOrganicaId' => ['nullable', 'exists:unidad_organicas,id'],
            'rolesSeleccionados' => ['array'],
            'rolesSeleccionados.*' => ['exists:roles,id'],
        ];
    }

    protected $messages = [
        'dni.digits' => 'El DNI debe tener 8 dígitos.',
    ];

    public function guardar(): void
    {
        $datos = $this->validate();

        $atributos = [
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'dni' => $datos['dni'],
            'email' => $datos['email'],
            'regimen' => $datos['regimen'],
            'sede_id' => $datos['sedeId'],
            'unidad_organica_id' => $datos['unidadOrganicaId'],
        ];

        if ($this->usuario) {
            // Reseteo manual opcional: si el admin llenó el campo, se
            // le pedirá actualizarla de nuevo en su próximo ingreso.
            if (filled($datos['password'])) {
                $atributos['password'] = Hash::make($datos['password']);
                $atributos['debe_actualizar_password'] = true;
            }

            $this->usuario->update($atributos);
        } else {
            // Alta nueva: la contraseña inicial siempre es el DNI,
            // nunca lo que se haya escrito en el campo (que ni
            // siquiera se muestra en este caso, ver la vista).
            $atributos['password'] = Hash::make($datos['dni']);
            $atributos['debe_actualizar_password'] = true;

            $this->usuario = User::create($atributos);
        }

        $this->usuario->syncRoles($datos['rolesSeleccionados']);

        session()->flash('mensaje', $this->usuario->wasRecentlyCreated ? 'Usuario creado.' : 'Usuario actualizado.');

        $this->redirectRoute('usuarios-admin.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.usuarios.usuario-admin-form', [
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'),
            'unidades' => UnidadOrganica::orderBy('nombre')->pluck('nombre', 'id'),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
