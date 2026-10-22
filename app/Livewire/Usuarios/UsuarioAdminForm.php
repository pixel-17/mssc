<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Events\PapeletaActualizada;
use App\Livewire\Concerns\RequiereAdmin;
use App\Models\ConfiguracionTurno;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use App\Support\ReglasDatosUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
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

    /** Modo «Nuevo administrador» (?tipo=admin). Se fija en mount(): en las peticiones de Livewire ya no existe el query string. */
    #[Locked]
    public bool $nuevoAdmin = false;

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
            $this->nuevoAdmin = true;
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

        // Cambió de régimen (p. ej. 276 -> 728): el turno que tenía era del régimen
        // anterior y se retira al guardar, así que aquí se programa el nuevo.
        if ($this->cambioDeRegimen()) {
            return true;
        }

        return ! ConfiguracionTurno::where('user_id', $this->usuario->id)->exists();
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

        if ($this->nuevoAdmin) {
            // «Nuevo administrador» tiene rol fijo y no pertenece a régimen,
            // sede ni unidad: se recalculan aquí (no solo en mount()) para
            // que un payload de Livewire manipulado no pueda colar otro rol
            // u otros datos — el formulario ya no muestra esos campos.
            $adminId = (int) Role::where('name', 'admin')->value('id');
            $this->rolesSeleccionados = $adminId > 0 ? [(string) $adminId] : [];
            $this->regimen = '';
            $this->sedeId = null;
            $this->unidadOrganicaId = null;
        }

        $requiereTurno = $this->admiteConfiguracionTurno() && $this->turno !== '';

        $datos = $this->validate();

        $adminId = (int) Role::where('name', 'admin')->value('id');
        $trabajadorId = (int) Role::where('name', 'trabajador')->value('id');
        $roles = array_map('intval', $datos['rolesSeleccionados']);

        if (! $this->validarRoles($roles, $adminId, $trabajadorId)) {
            return;
        }

        if (! $this->validarUnidad($datos, $adminId, $roles)) {
            return;
        }

        if (! $this->validarUnicoRrhhActivo($datos)) {
            return;
        }

        if (! $this->validarUnicoAdminActivo($datos)) {
            return;
        }

        if (! $this->validarDesactivacion($datos)) {
            return;
        }

        $atributos = $this->construirAtributos($datos, $roles, $adminId);

        // Se calcula ANTES de guardar: después $this->usuario ya trae los valores nuevos.
        $debeRetirarTurno = $this->usuario !== null
            && ($this->cambioDeRegimen() || ($this->usuario->activo && ! $datos['activo']));

        DB::transaction(fn () => $this->guardarUsuario($atributos, $datos, $requiereTurno, $generador, $debeRetirarTurno));

        session()->flash('mensaje', $this->usuario->wasRecentlyCreated ? 'Usuario creado.' : 'Usuario actualizado.');

        $this->redirectRoute('usuarios-admin.index', navigate: false);
    }

    /**
     * Un administrador no lleva régimen, no puede ser también trabajador, y
     * no puede dárselo a quien encabeza una unidad sin antes reasignarla.
     *
     * @param  list<int>  $roles
     */
    private function validarRoles(array $roles, int $adminId, int $trabajadorId): bool
    {
        // Un administrador no lleva régimen: si el alta es solo de admin, el régimen debe quedar vacío.
        if ($this->esSoloAdmin() && $this->regimen !== '') {
            $this->addError('rolesSeleccionados', 'Un administrador no lleva régimen: deja el régimen vacío.');

            return false;
        }

        if (in_array($adminId, $roles, true) && in_array($trabajadorId, $roles, true)) {
            $this->addError('rolesSeleccionados', 'Un administrador no puede tener también el rol de trabajador: no tiene papeletas, equipo ni jefe.');

            return false;
        }

        if (in_array($adminId, $roles, true) && $this->usuario?->unidadesQueEncabeza()->exists()) {
            $this->addError('rolesSeleccionados', 'Encabeza una unidad: reasigna primero esa jefatura antes de darle el rol de administrador.');

            return false;
        }

        return true;
    }

    /**
     * Igual que MoverTrabajadorAction::validar(): quien encabeza una unidad
     * (jefe_id) no se mueve de unidad_organica_id por este formulario sin
     * reasignar antes esa jefatura, o queda «duplicado» en el organigrama:
     * sigue de jefe en la unidad vieja (jefe_id no se tocó) y aparece
     * también como miembro en la nueva. El caso «se le da el rol admin»
     * ya lo bloquea validarRoles() antes de llegar aquí.
     *
     * @param  list<int>  $roles
     */
    private function validarUnidad(array $datos, int $adminId, array $roles): bool
    {
        if (in_array($adminId, $roles, true)) {
            return true;
        }

        if ($this->usuario
            && $this->usuario->unidad_organica_id !== null
            && (int) $datos['unidadOrganicaId'] !== (int) $this->usuario->unidad_organica_id
            && $this->usuario->unidadesQueEncabeza()->exists()
        ) {
            $this->addError('unidadOrganicaId', 'Encabeza una unidad: reasigna primero esa jefatura antes de moverlo a otra unidad.');

            return false;
        }

        return true;
    }

    /** Si es el único RR. HH. activo, no se lo puede desactivar ni quitarle el rol. */
    private function validarUnicoRrhhActivo(array $datos): bool
    {
        if (! $this->usuario?->esUnicoRrhhActivo()) {
            return true;
        }

        $rolRrhhId = (int) Role::where('name', 'rrhh')->value('id');
        $conservaRol = in_array($rolRrhhId, array_map('intval', $datos['rolesSeleccionados']), true);

        if (! $datos['activo']) {
            $this->addError('activo', 'Es el único usuario de RR. HH. activo: sin él, toda papeleta aprobada por el jefe se autorizaría sola. Designa primero a otra persona con rol RR. HH.');

            return false;
        }

        if (! $conservaRol) {
            $this->addError('rolesSeleccionados', 'Es el único usuario de RR. HH. activo: no puedes quitarle ese rol hasta designar a otra persona.');

            return false;
        }

        return true;
    }

    /** Si es el único administrador activo, no se lo puede desactivar ni quitarle el rol. */
    private function validarUnicoAdminActivo(array $datos): bool
    {
        if (! $this->usuario?->esUnicoAdminActivo()) {
            return true;
        }

        $rolAdminId = (int) Role::where('name', 'admin')->value('id');
        $conservaRol = in_array($rolAdminId, array_map('intval', $datos['rolesSeleccionados']), true);

        if (! $datos['activo']) {
            $this->addError('activo', 'Es el único administrador activo: sin él nadie puede gestionar el sistema. Designa primero a otro administrador.');

            return false;
        }

        if (! $conservaRol) {
            $this->addError('rolesSeleccionados', 'Es el único administrador activo: no puedes quitarle ese rol hasta crear o activar a otro administrador.');

            return false;
        }

        return true;
    }

    /**
     * Mismas reglas que desactivar desde la lista o el organigrama (jefe
     * con personas a cargo, etc.).
     */
    private function validarDesactivacion(array $datos): bool
    {
        if (! ($this->usuario?->activo && ! $datos['activo'])) {
            return true;
        }

        $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar(Auth::user(), $this->usuario);

        if ($motivo !== null) {
            $this->addError('activo', $motivo);

            return false;
        }

        // Un jefe con personas a cargo no se desactiva desde el formulario:
        // hay que elegir su reemplazo (baja con reemplazo, en Usuarios).
        $cargo = app(CambiarEstadoUsuarioAction::class)->jefaturasPorResolver($this->usuario);

        if ($cargo->isNotEmpty()) {
            $this->addError('activo', 'Tiene personas a cargo: desactívalo desde Usuarios para elegir a quién reemplaza.');

            return false;
        }

        return true;
    }

    /**
     * @param  list<int>  $roles
     * @return array<string, mixed>
     */
    private function construirAtributos(array $datos, array $roles, int $adminId): array
    {
        return [
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'dni' => $datos['dni'],
            'email' => $datos['email'],
            'regimen' => $datos['regimen'] ?: null,
            // Un administrador no pertenece a ninguna sede ni unidad del organigrama.
            'sede_id' => in_array($adminId, $roles, true) ? null : $datos['sedeId'],
            'unidad_organica_id' => in_array($adminId, $roles, true) ? null : $datos['unidadOrganicaId'],
            'activo' => $datos['activo'],
        ];
    }

    /**
     * Crea o actualiza al usuario, sincroniza roles y carga el turno si
     * corresponde. Debe correr dentro de DB::transaction (ver guardar()).
     *
     * @param  array<string, mixed>  $atributos
     * @param  array<string, mixed>  $datos
     */
    private function guardarUsuario(array $atributos, array $datos, bool $requiereTurno, GeneradorTurnoMensualService $generador, bool $debeRetirarTurno): void
    {
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

            // Desactivado o con otro régimen: el turno anterior ya no le corresponde.
            if ($debeRetirarTurno) {
                $generador->retirarTurno($this->usuario);
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
        PapeletaActualizada::olvidarDestinatariosFijos();

        if ($requiereTurno && $datos['activo']) {
            $generador->cargarConfiguracion(
                trabajador: $this->usuario,
                turno: $datos['turno'],
                fechaAncla: Carbon::parse($datos['fechaAncla']),
                actor: Auth::user(),
                diasTrabajo: $datos['diasTrabajo'],
                diasDescanso: $datos['diasDescanso'],
            );
        }
    }

    /**
     * Cuenta ya registrada con el DNI escrito (activa o desactivada). Solo se
     * busca al crear y con el DNI completo; al editar no aplica y devuelve null.
     */
    protected function buscarCuentaExistente(): ?User
    {
        if ($this->usuario !== null || strlen($this->dni) !== 8) {
            return null;
        }

        return User::where('dni', $this->dni)->first();
    }

    public function render(): View
    {
        return view('livewire.usuarios.usuario-admin-form', [
            'cuentaExistente' => $this->buscarCuentaExistente(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'),
            'unidades' => UnidadOrganica::orderBy('nombre')->pluck('nombre', 'id'),
            // En «Nuevo usuario» no se ofrece admin: los administradores se crean desde «Nuevo administrador».
            // En «Nuevo administrador» el rol es fijo: solo se trae 'admin' (ver blade, que ni siquiera pinta el selector).
            'roles' => Role::orderBy('name')
                ->when($this->nuevoAdmin, fn ($q) => $q->where('name', 'admin'))
                ->when($this->usuario === null && ! $this->nuevoAdmin, fn ($q) => $q->where('name', '!=', 'admin'))
                ->get(),
            'esAdminRol' => $this->incluyeAdmin(),
            'requiereTurno' => $this->usuario !== null && $this->admiteConfiguracionTurno(),
            'opcionesTurno' => $this->regimen
                ? ConfiguracionTurno::turnosValidosPara(new User(['regimen' => $this->regimen]))
                : [],
        ]);
    }
}