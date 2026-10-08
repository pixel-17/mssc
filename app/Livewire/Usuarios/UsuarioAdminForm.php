<?php

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Models\ConfiguracionTurno;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Support\ReglasDatosUsuario;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\RequiereAdmin;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Formulario de Usuarios. Gestión SOLO para
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
#[Title('Usuario')]
class UsuarioAdminForm extends Component
{
    use RequiereAdmin;

    #[Locked]
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

    public bool $activo = true;

    public string $turno = '';

    public string $fechaAncla = '';

    public int $diasTrabajo = 6;

    public int $diasDescanso = 1;

    public function mount(?User $usuario = null): void
    {
        // En un alta nueva fechaAncla queda vacía: el turno es opcional al crear.
        if ($usuario?->exists) {
            // Usuario existente sin configuración de turno: sugerimos hoy.
            $this->fechaAncla = now()->toDateString();
            $this->usuario = $usuario;
            $this->name = $usuario->name;
            $this->apellido = $usuario->apellido;
            $this->dni = $usuario->dni;
            $this->email = $usuario->email;
            $this->regimen = $usuario->regimen ?? '';
            $this->sedeId = $usuario->sede_id;
            $this->unidadOrganicaId = $usuario->unidad_organica_id;
            $this->rolesSeleccionados = $usuario->roles->pluck('id')->all();
            $this->activo = $usuario->activo;

            $config = ConfiguracionTurno::where('user_id', $usuario->id)->first();

            if ($config) {
                $this->turno = $config->turno;
                $this->fechaAncla = $config->fecha_ancla->toDateString();
                $this->diasTrabajo = $config->dias_trabajo;
                $this->diasDescanso = $config->dias_descanso;
            }
        } elseif (request()->query('tipo') === 'admin') {
            // «Nuevo administrador»: rol admin ya marcado y sin unidad (el admin no pertenece al organigrama).
            $adminId = (int) Role::where('name', 'admin')->value('id');
            $this->rolesSeleccionados = $adminId > 0 ? [(string) $adminId] : [];
        } else {
            // Alta desde el organigrama: ?unidad=ID preselecciona la unidad
            // y, si su jefe tiene sede, la propone como sede.
            $unidadId = request()->integer('unidad') ?: null;
            $unidad = $unidadId ? UnidadOrganica::with('jefe')->find($unidadId) : null;

            if ($unidad) {
                $this->unidadOrganicaId = $unidad->id;
                $this->sedeId = $unidad->jefe?->sede_id;
            }
        }
    }

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

    /**
     * ¿Se puede cargar el turno inicial desde este formulario? Es OPCIONAL: al crear no se
     * pide (lo programan después); si llega, se valida y se carga. Un 728 sin turno no puede
     * crear papeletas (ver CrearPapeletaAction) hasta que se lo carguen.
     */
    protected function admiteConfiguracionTurno(): bool
    {
        if ($this->regimen !== '728') {
            return false;
        }

        if (! $this->usuario) {
            return true;
        }

        return ! ConfiguracionTurno::where('user_id', $this->usuario->id)->exists();
    }

    protected function rules(): array
    {
        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ReglasDatosUsuario::dni($this->usuario?->id, soloActivos: false),
            'email' => ReglasDatosUsuario::email($this->usuario?->id),
            'password' => ['nullable', 'string', 'min:8'],
            'regimen' => [$this->esSoloAdmin() ? 'nullable' : 'required', 'in:276,728'],
            'sedeId' => ['nullable', 'exists:sedes,id'],
            'unidadOrganicaId' => ['nullable', 'exists:unidad_organicas,id'],
            'rolesSeleccionados' => ['required', 'array', 'min:1'],
            'rolesSeleccionados.*' => ['exists:roles,id'],
            'activo' => ['boolean'],
        ];

        if ($this->admiteConfiguracionTurno() && $this->turno !== '') {
            $reglas['turno'] = ['required', 'in:'.implode(',', ConfiguracionTurno::turnosValidosPara(new User(['regimen' => $this->regimen])))];
            $reglas['fechaAncla'] = ['required', 'date'];
            $reglas['diasTrabajo'] = ['required', 'integer', 'min:1', 'max:30'];
            $reglas['diasDescanso'] = ['required', 'integer', 'min:1', 'max:30'];
        }

        return $reglas;
    }

    protected $messages = [
        'rolesSeleccionados.required' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'rolesSeleccionados.min' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'dni.digits' => 'El DNI debe tener 8 dígitos.',
        'dni.unique' => 'Ese DNI ya tiene una cuenta. Si está desactivada, reactívala desde Usuarios.',
    ];

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        $this->autorizarAdmin();

        $requiereTurno = $this->admiteConfiguracionTurno() && $this->turno !== '';

        $datos = $this->validate();

        $adminId = (int) Role::where('name', 'admin')->value('id');
        $trabajadorId = (int) Role::where('name', 'trabajador')->value('id');
        $roles = array_map('intval', $datos['rolesSeleccionados']);

        if (in_array($adminId, $roles, true) && in_array($trabajadorId, $roles, true)) {
            $this->addError('rolesSeleccionados', 'Un administrador no puede tener también el rol de trabajador: no tiene papeletas, equipo ni jefe.');

            return;
        }

        if (in_array($adminId, $roles, true) && $this->usuario?->unidadesQueEncabeza()->exists()) {
            $this->addError('rolesSeleccionados', 'Encabeza una unidad: reasigna primero esa jefatura antes de darle el rol de administrador.');

            return;
        }

        if ($this->usuario?->esUnicoRrhhActivo()) {
            $rolRrhhId = (int) Role::where('name', 'rrhh')->value('id');
            $conservaRol = in_array($rolRrhhId, array_map('intval', $datos['rolesSeleccionados']), true);

            if (! $datos['activo']) {
                $this->addError('activo', 'Es el único usuario de RR. HH. activo: sin él, toda papeleta aprobada por el jefe se autorizaría sola. Designa primero a otra persona con rol RR. HH.');

                return;
            }

            if (! $conservaRol) {
                $this->addError('rolesSeleccionados', 'Es el único usuario de RR. HH. activo: no puedes quitarle ese rol hasta designar a otra persona.');

                return;
            }
        }

        if (! $datos['activo'] && $this->usuario?->esJefeInmediatoDeAlgunTurno()) {
            $this->addError('activo', 'Es jefe inmediato de un turno (MAÑANA/TARDE/NOCHE) en su unidad: reasigna primero ese turno a otro jefe antes de desactivarlo.');

            return;
        }

        // Mismas reglas que desactivar desde la lista o el organigrama (jefe con personas a cargo, etc.).
        if ($this->usuario?->activo && ! $datos['activo']) {
            $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar(Auth::user(), $this->usuario);

            if ($motivo !== null) {
                $this->addError('activo', $motivo);

                return;
            }
        }

        $atributos = [
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'dni' => $datos['dni'],
            'email' => $datos['email'],
            'regimen' => $datos['regimen'] ?: null,
            'sede_id' => $datos['sedeId'],
            // Un administrador no pertenece a ninguna unidad del organigrama.
            'unidad_organica_id' => in_array($adminId, $roles, true) ? null : $datos['unidadOrganicaId'],
            'activo' => $datos['activo'],
        ];

        DB::transaction(function () use ($atributos, $datos, $requiereTurno, $generador) {
            if ($this->usuario) {
                // Reseteo manual opcional: si el admin llenó el campo, se
                // le pedirá actualizarla de nuevo en su próximo ingreso.
                if (filled($datos['password'])) {
                    $atributos['password'] = Hash::make($datos['password']);
                    $atributos['debe_actualizar_password'] = true;
                }

                $this->usuario->update($atributos);

                if (! $datos['activo']) {
                    $this->usuario->tokens()->delete();
                }
            } else {
                // Alta nueva: la contraseña inicial siempre es el DNI,
                // nunca lo que se haya escrito en el campo (que ni
                // siquiera se muestra en este caso, ver la vista).
                $atributos['password'] = Hash::make($datos['dni']);
                $atributos['debe_actualizar_password'] = true;

                $this->usuario = User::create($atributos);
            }

            // Los checkboxes de Livewire llegan como strings ("3"): Spatie los tomaría por
            // NOMBRE de rol (RoleDoesNotExist). Con enteros los busca por id.
            $this->usuario->syncRoles(array_map('intval', $datos['rolesSeleccionados']));

            if ($requiereTurno) {
                $generador->cargarConfiguracion(
                    trabajador: $this->usuario,
                    turno: $datos['turno'],
                    fechaAncla: Carbon::parse($datos['fechaAncla']),
                    actor: Auth::user(),
                    diasTrabajo: $datos['diasTrabajo'],
                    diasDescanso: $datos['diasDescanso'],
                );
            }
        });

        session()->flash('mensaje', $this->usuario->wasRecentlyCreated ? 'Usuario creado.' : 'Usuario actualizado.');

        $this->redirectRoute('usuarios-admin.index', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.usuarios.usuario-admin-form', [
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'),
            'unidades' => UnidadOrganica::orderBy('nombre')->pluck('nombre', 'id'),
            // En «Nuevo usuario» no se ofrece admin: los administradores se crean desde «Nuevo administrador».
            'roles' => Role::orderBy('name')
                ->when($this->usuario === null && ! $this->soloAdmin, fn ($q) => $q->where('name', '!=', 'admin'))
                ->get(),
            'esAdminRol' => $this->incluyeAdmin(),
            'nuevoAdmin' => $this->usuario === null && request()->query('tipo') === 'admin',
            'requiereTurno' => $this->usuario !== null && $this->admiteConfiguracionTurno(),
            'opcionesTurno' => $this->regimen
                ? ConfiguracionTurno::turnosValidosPara(new User(['regimen' => $this->regimen]))
                : [],
        ]);
    }
}
