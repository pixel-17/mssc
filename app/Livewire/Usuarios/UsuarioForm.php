<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Actions\Usuario\CrearUsuarioAction;
use App\Actions\Usuario\EditarUsuarioAction;
use App\Events\PapeletaActualizada;
use App\Exceptions\UsuarioException;
use App\Livewire\Usuarios\Concerns\TieneRolesDeUsuario;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use App\Support\ReglasDatosUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * ÚNICO formulario de usuarios (alta y edición). Lo que cada quien puede
 * hacer depende de su ALCANCE, calculado en el servidor en mount() y
 * recalculado al guardar — nunca viene del cliente:
 *
 *  - admin   → todo. Cualquier unidad/sede, roles, contraseña, activo.
 *  - area    → Jefe de Área. Igual que admin PERO solo dentro de sus unidades
 *              (la que encabeza + sub-unidades): sin tocar roles ni contraseñas,
 *              sin crear administradores. Al crear puede elegir Trabajador o
 *              Jefe Inmediato.
 *  - equipo  → Jefe Inmediato "puro". Crea trabajadores en su propia unidad
 *              (heredan unidad y sede) y solo corrige nombre/apellido/correo
 *              de los suyos.
 *
 * La lógica de quién puede qué sobre quién vive en UserPolicy; aquí solo se
 * aplica esa política.
 *
 * jefe_inmediato_id / jefe_area_id NO se editan aquí: se recalculan solos
 * vía UserObserver al guardar unidad_organica_id. Para que alguien sea Jefe
 * Inmediato de una unidad, asígnalo como jefe_id de esa unidad desde el
 * organigrama (UnidadModal).
 *
 * Contraseña: al crear, siempre es el DNI y se marca debe_actualizar_password
 * para que RedirigirSiDebeActualizarPassword se la pida en el primer ingreso.
 * Al editar, solo el admin puede resetearla (también la marca).
 */
#[Layout('layouts.app')]
#[Title('Usuario')]
class UsuarioForm extends Component
{
    use TieneRolesDeUsuario;

    #[Locked]
    public ?User $usuario = null;

    /** admin | area | equipo. Se fija en mount() y se recalcula en guardar(). */
    #[Locked]
    public string $alcance = 'admin';

    public string $name = '';

    public string $apellido = '';

    public string $dni = '';

    public string $email = '';

    public string $password = '';

    public string $regimen = '';

    /**
     * Aquí el turno no se pide ni se edita (ver vista: se carga después desde
     * Turnos). Existe solo para que TieneRolesDeUsuario::updatedRegimen()
     * pueda limpiarlo sin crear una propiedad dinámica si alguna vez se
     * llegara a usar en este formulario.
     */
    public string $turno = '';

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    /** @var array<int, int|string> */
    public array $rolesSeleccionados = [];

    public bool $activo = true;

    /** Solo alcance area, al crear: trabajador | jefe_inmediato. */
    public string $tipo = 'trabajador';

    /** Modo «Nuevo administrador» (?tipo=admin). Fijado en mount(). */
    #[Locked]
    public bool $nuevoAdmin = false;

    /** Volver al organigrama (?volver=organigrama) en vez de a la lista. Fijado en mount(). */
    #[Locked]
    public bool $volverAlOrganigrama = false;

    // ─────────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────────

    public function mount(?User $usuario = null): void
    {
        $existente = $usuario?->exists ? $usuario : null;

        $this->alcance = $this->resolverAlcance($existente);
        // Viene del organigrama (ver persona-drawer.blade.php, ?volver=organigrama):
        // el botón «Cancelar» debe reabrir la ficha de la persona, no ir a la lista.
        $this->volverAlOrganigrama = request()->query('volver') === 'organigrama';

        if ($existente) {
            $this->usuario = $existente;
            $this->name = $existente->name;
            $this->apellido = $existente->apellido;
            $this->dni = $existente->dni;
            $this->email = $existente->email;
            $this->regimen = $existente->regimen ?? '';
            $this->sedeId = $existente->sede_id;
            $this->unidadOrganicaId = $existente->unidad_organica_id;
            $this->rolesSeleccionados = $existente->roles->pluck('id')->all();
            $this->activo = $existente->activo;

            return;
        }

        // ── Alta nueva ──────────────────────────────────────────────

        if ($this->esAdmin() && request()->query('tipo') === 'admin') {
            $this->nuevoAdmin = true;
            $adminId = (int) Role::where('name', 'admin')->value('id');
            $this->rolesSeleccionados = $adminId > 0 ? [(string) $adminId] : [];

            return;
        }

        if ($this->esEquipo()) {
            return;
        }

        // Preselección desde el organigrama (?unidad=ID).
        $unidadId = request()->integer('unidad') ?: null;

        if ($unidadId && $this->esArea() && ! in_array($unidadId, $this->idsDeArea(), true)) {
            $unidadId = null;
        }

        $unidad = $unidadId ? UnidadOrganica::with('jefe')->find($unidadId) : null;

        if ($unidad) {
            $this->unidadOrganicaId = $unidad->id;
            $this->sedeId = $unidad->jefe?->sede_id;
        }

        if ($this->esArea()) {
            $this->regimen = (string) (Auth::user()?->regimen ?? '');
            $this->sedeId ??= Auth::user()?->sede_id;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Alcance
    // ─────────────────────────────────────────────────────────────────

    /**
     * Alcance del actor sobre $objetivo (null = alta nueva).
     * Aborta con 403 si no tiene ninguno.
     */
    protected function resolverAlcance(?User $objetivo): string
    {
        /** @var User $actor */
        $actor = Auth::user();

        // El bypass de admin aplica a EDITAR cualquier usuario y a CREAR
        // OTRO ADMIN (?tipo=admin / botón «Nuevo administrador»).
        // Un admin que crea sin esa query (alta de jefes/trabajadores) sigue
        // el flujo normal, que lo rechaza (UserPolicy::puedeCrearAlgo).
        //
        // Nota: ?tipo=admin solo llega en el GET inicial; el POST de guardar()
        // va a /livewire/update sin query string. $this->nuevoAdmin se fija en
        // mount() y viaja en el snapshot de Livewire, cubriendo ese caso.
        if ($actor->hasRole('admin') && ($objetivo || request()->query('tipo') === 'admin' || $this->nuevoAdmin)) {
            return 'admin';
        }

        if ($objetivo) {
            abort_unless($actor->can('editar', $objetivo), 403);

            return $objetivo->unidad_organica_id
                && in_array((int) $objetivo->unidad_organica_id, $this->idsDeArea($actor), true)
                    ? 'area'
                    : 'equipo';
        }

        if ($actor->esJefeDeArea()) {
            return 'area';
        }

        abort_unless($actor->can('puedeCrearAlgo', User::class), 403);

        return 'equipo';
    }

    protected function esAdmin(): bool
    {
        return $this->alcance === 'admin';
    }

    protected function esArea(): bool
    {
        return $this->alcance === 'area';
    }

    protected function esEquipo(): bool
    {
        return $this->alcance === 'equipo';
    }

    /**
     * IDs de todas las unidades donde el actor actúa como Jefe de Área:
     * las que encabeza + todos sus descendientes.
     *
     * @return array<int, int>
     */
    protected function idsDeArea(?User $actor = null): array
    {
        $actor ??= Auth::user();

        if ($actor === null) {
            return [];
        }

        $ids = collect();

        foreach ($actor->unidadesDeArea as $u) {
            $ids->push($u->id);
            $ids = $ids->merge($u->descendantIds());
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers de roles y turno
    // ─────────────────────────────────────────────────────────────────
    // esSoloAdmin(), incluyeAdmin(), cambioDeRegimen() y updatedRegimen()
    // viven en TieneRolesDeUsuario (ver arriba): son iguales acá y en
    // UsuarioAdminForm.

    // ─────────────────────────────────────────────────────────────────
    // Validación
    // ─────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $creando = $this->usuario === null;

        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ReglasDatosUsuario::email($this->usuario?->id),
        ];

        // DNI: solo lo edita el admin o al crear (el Jefe Inmediato no lo toca al editar).
        if ($creando || ! $this->esEquipo()) {
            $reglas['dni'] = ReglasDatosUsuario::dni(
                $this->usuario?->id,
                soloActivos: $creando && ! $this->esAdmin(),
            );
        }

        // Régimen: admin siempre (excepto si es solo admin), demás roles al crear o al editar como área.
        if (! $this->esEquipo() || $creando) {
            $reglas['regimen'] = [
                $this->esAdmin() && $this->esSoloAdmin() ? 'nullable' : 'required',
                'in:276,728',
            ];
        }

        if ($this->esAdmin()) {
            $reglas['password'] = ['nullable', 'string', 'min:8'];
            $reglas['sedeId'] = ['nullable', 'exists:sedes,id'];
            $reglas['unidadOrganicaId'] = ['nullable', 'exists:unidad_organicas,id'];
            $reglas['rolesSeleccionados'] = ['required', 'array', 'min:1'];
            $reglas['rolesSeleccionados.*'] = ['exists:roles,id'];
            $reglas['activo'] = ['boolean'];
        }

        if ($this->esArea()) {
            $idsArea = $this->idsDeArea();
            $reglas['unidadOrganicaId'] = ['required', 'in:'.implode(',', $idsArea ?: [0])];
            $reglas['sedeId'] = ($creando && $this->tipo === 'trabajador')
                ? ['nullable', 'exists:sedes,id']
                : ['required', 'exists:sedes,id'];

            if ($creando) {
                $reglas['tipo'] = ['required', 'in:trabajador,jefe_inmediato'];
            } else {
                $reglas['activo'] = ['boolean'];
            }
        }

        // Jefe Inmediato "puro": puede activar/desactivar, cambiar sede y régimen
        // a sus propios trabajadores al editarlos.
        if ($this->esEquipo() && ! $creando) {
            $reglas['activo'] = ['boolean'];
            $reglas['sedeId'] = ['nullable', 'exists:sedes,id'];
            $reglas['regimen'] = ['required', 'in:276,728'];
        }

        return $reglas;
    }

    /** @var array<string, string> */
    protected $messages = [
        'rolesSeleccionados.required' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'rolesSeleccionados.min' => 'Selecciona al menos un rol: un usuario sin rol no puede usar el sistema.',
        'dni.digits' => 'El DNI debe tener 8 dígitos.',
        'dni.unique' => 'Ese DNI ya tiene una cuenta. Si está desactivada, reactívala desde Usuarios.',
        'unidadOrganicaId.in' => 'Esa unidad no pertenece a tu área.',
        'sedeId.required' => 'Indica la sede: es necesaria para los cierres.',
    ];

    // ─────────────────────────────────────────────────────────────────
    // Guardar
    // ─────────────────────────────────────────────────────────────────

    public function guardar(GeneradorTurnoMensualService $generador): void
    {
        // Recalcular: el rol o la jefatura pudo cambiar con la pestaña abierta.
        $this->alcance = $this->resolverAlcance($this->usuario);

        $datos = $this->validate();

        if ($this->usuario === null && ! $this->esAdmin()) {
            $this->guardarComoJefe($datos);

            return;
        }

        if ($this->esEquipo()) {
            $this->guardarComoJefeInmediato($datos);

            return;
        }

        $this->guardarComoAdminOArea($datos, $generador);
    }

    /**
     * Alta por Jefe de Área o Jefe Inmediato. Delega en CrearUsuarioAction,
     * que ya conoce las reglas de reingreso, jefatura de unidad y sede heredada.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function guardarComoJefe(array $datos): void
    {
        /** @var User $actor */
        $actor = Auth::user();
        $esArea = $this->esArea();

        if ($esArea) {
            abort_unless($actor->can('crearEnUnidad', [User::class, (int) $this->unidadOrganicaId]), 403);
        } else {
            abort_unless($actor->can('puedeCrearAlgo', User::class), 403);
        }

        try {
            $nuevo = app(CrearUsuarioAction::class)->ejecutar($actor, [
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'dni' => $datos['dni'],
                'email' => $datos['email'],
                'regimen' => $datos['regimen'],
                'sede_id' => $esArea ? $this->sedeId : null,
                'unidad_organica_id' => $esArea ? $this->unidadOrganicaId : null,
                'tipo' => $esArea ? $this->tipo : 'trabajador',
                'turno' => null,
                'fecha_ancla' => null,
                'dias_trabajo' => null,
                'dias_descanso' => null,
            ], $esArea);
        } catch (UsuarioException $e) {
            $this->addError('dni', $e->getMessage());

            return;
        }

        session()->flash('mensaje', "Usuario {$nuevo->nombre_completo} creado correctamente.");

        if ($nuevo->regimen === '728' && $this->tipo !== 'jefe_inmediato') {
            session()->flash('warning', "{$nuevo->nombre_completo} es 728 y todavía no tiene turno: cárgalo en Turnos; hasta entonces no podrá crear papeletas.");
        }

        $this->redirectRoute('usuarios.index', navigate: false);
    }

    /**
     * Jefe Inmediato editando a uno de los suyos: nombre, apellido, correo
     * y (desde ahora) activo/desactivado, con las mismas guardias que ya
     * aplican a admin y Jefe de Área (no desactivarse a sí mismo —no aplica
     * aquí—, no al único RR. HH. activo, resolver jefaturas pendientes si
     * encabezara alguna unidad).
     *
     * @param  array<string, mixed>  $datos
     */
    protected function guardarComoJefeInmediato(array $datos): void
    {
        if ($this->usuario === null) {
            return;
        }

        $usuario = $this->usuario;

        /** @var User $actor */
        $actor = Auth::user();

        abort_unless($actor->can('editar', $usuario), 403);

        $activo = (bool) ($datos['activo'] ?? $usuario->activo);

        if ($usuario->activo && ! $activo) {
            $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar($actor, $usuario);

            if ($motivo !== null) {
                $this->addError('activo', $motivo);

                return;
            }

            $cargo = app(CambiarEstadoUsuarioAction::class)->jefaturasPorResolver($usuario);

            if ($cargo->isNotEmpty()) {
                $this->addError('activo', 'Tiene personas a cargo: desactívalo desde Usuarios para elegir su reemplazo.');

                return;
            }
        }

        $debeRetirarTurno = $usuario->activo && ! $activo;

        DB::transaction(function () use ($datos, $activo, $debeRetirarTurno, $usuario) {
            app(EditarUsuarioAction::class)->ejecutar($usuario, [
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'email' => $datos['email'],
                'sede_id' => $usuario->sede_id,
                'unidad_organica_id' => $usuario->unidad_organica_id,
            ], false);

            // El Jefe Inmediato puede cambiar sede y régimen de sus trabajadores.
            $usuario->update([
                'sede_id' => $datos['sedeId'] ?? $usuario->sede_id,
                'regimen' => $datos['regimen'] ?? $usuario->regimen,
            ]);

            $usuario->update(['activo' => $activo]);

            if (! $activo) {
                $usuario->tokens()->delete();
            }

            if ($debeRetirarTurno) {
                app(GeneradorTurnoMensualService::class)->retirarTurno($usuario);
            }
        });

        session()->flash('mensaje', "Usuario {$usuario->nombre_completo} actualizado.");

        $this->redirectRoute('usuarios.index', navigate: false);
    }

    /**
     * Admin (alta/edición completa) o Jefe de Área editando (sin roles ni contraseña).
     *
     * @param  array<string, mixed>  $datos
     */
    protected function guardarComoAdminOArea(array $datos, GeneradorTurnoMensualService $generador): void
    {
        $esAdmin = $this->esAdmin();
        $adminId = (int) Role::where('name', 'admin')->value('id');
        $trabajadorId = (int) Role::where('name', 'trabajador')->value('id');

        // Jefe de Área no modifica roles: conserva los actuales.
        $roles = $esAdmin
            ? array_map('intval', $datos['rolesSeleccionados'])
            : ($this->usuario?->roles ?? collect())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $activo = ($esAdmin || $this->esArea())
            ? (bool) ($datos['activo'] ?? $this->usuario->activo ?? true)
            : true;

        // ── Guardias de negocio (solo admin) ────────────────────────

        if ($esAdmin) {
            if ($this->esSoloAdmin() && $this->regimen !== '') {
                $this->addError('rolesSeleccionados', 'Un administrador no lleva régimen: deja el campo vacío.');

                return;
            }

            if (in_array($adminId, $roles, true) && in_array($trabajadorId, $roles, true)) {
                $this->addError('rolesSeleccionados', 'Un administrador no puede tener también el rol de trabajador.');

                return;
            }

            if (in_array($adminId, $roles, true) && $this->usuario?->unidadesQueEncabeza()->exists()) {
                $this->addError('rolesSeleccionados', 'Encabeza una unidad: reasigna primero esa jefatura antes de darle el rol de administrador.');

                return;
            }

            if ($this->usuario?->esUnicoRrhhActivo()) {
                $rolRrhhId = (int) Role::where('name', 'rrhh')->value('id');

                if (! $activo) {
                    $this->addError('activo', 'Es el único usuario de RR. HH. activo. Designa primero a otra persona.');

                    return;
                }

                if (! in_array($rolRrhhId, $roles, true)) {
                    $this->addError('rolesSeleccionados', 'Es el único usuario de RR. HH. activo: no puedes quitarle ese rol.');

                    return;
                }
            }

            if ($this->usuario?->esUnicoAdminActivo()) {
                if (! $activo) {
                    $this->addError('activo', 'Es el único administrador activo. Designa primero a otro.');

                    return;
                }

                if (! in_array($adminId, $roles, true)) {
                    $this->addError('rolesSeleccionados', 'Es el único administrador activo: no puedes quitarle ese rol.');

                    return;
                }
            }
        }

        // ── Guardia de unidad (admin y jefe de área) ────────────────
        // Igual que MoverTrabajadorAction::validar(): quien encabeza una
        // unidad (jefe_id) no se mueve de unidad_organica_id por este
        // camino sin reasignar antes esa jefatura, o queda «duplicado»
        // en el organigrama: sigue de jefe en la unidad vieja (jefe_id
        // no se tocó) y aparece también como miembro en la nueva.
        if ($this->usuario
            && $this->usuario->unidad_organica_id !== null
            && (int) $datos['unidadOrganicaId'] !== (int) $this->usuario->unidad_organica_id
            && $this->usuario->unidadesQueEncabeza()->exists()
        ) {
            $this->addError('unidadOrganicaId', 'Encabeza una unidad: reasigna primero esa jefatura antes de moverlo a otra unidad.');

            return;
        }

        // ── Guardia de desactivación (admin y jefe de área) ─────────

        if ($this->usuario?->activo && ! $activo) {
            /** @var User $actor */
            $actor = Auth::user();

            $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar($actor, $this->usuario);

            if ($motivo !== null) {
                $this->addError('activo', $motivo);

                return;
            }

            $cargo = app(CambiarEstadoUsuarioAction::class)->jefaturasPorResolver($this->usuario);

            if ($cargo->isNotEmpty()) {
                $this->addError('activo', 'Tiene personas a cargo: desactívalo desde Usuarios para elegir su reemplazo.');

                return;
            }
        }

        // ── Persistencia ─────────────────────────────────────────────

        $atributos = [
            'name' => $datos['name'],
            'apellido' => $datos['apellido'],
            'email' => $datos['email'],
            'activo' => $activo,
        ];

        if (! $this->esArea() || $this->usuario === null) {
            // El área no edita DNI/régimen/sede/unidad en su propio alta (eso va por guardarComoJefe).
            $atributos['dni'] = $datos['dni'];
            $atributos['regimen'] = $datos['regimen'] ?: null;
            $atributos['sede_id'] = $datos['sedeId'];
            $atributos['unidad_organica_id'] = in_array($adminId, $roles, true) ? null : $datos['unidadOrganicaId'];
        } else {
            // Jefe de Área editando: sí puede mover de unidad, cambiar sede y régimen.
            $atributos['sede_id'] = $datos['sedeId'];
            $atributos['unidad_organica_id'] = $datos['unidadOrganicaId'];
            $atributos['regimen'] = $datos['regimen'] ?: null;
        }

        $debeRetirarTurno = $this->usuario !== null
            && ($this->cambioDeRegimen() || ($this->usuario->activo && ! $activo));

        DB::transaction(function () use ($atributos, $datos, $generador, $debeRetirarTurno, $esAdmin, $activo, $roles) {
            if ($this->usuario) {
                if ($esAdmin && filled($datos['password'] ?? null)) {
                    $atributos['password'] = Hash::make($datos['password']);
                    $atributos['debe_actualizar_password'] = true;
                }

                $this->usuario->update($atributos);

                if (! $activo) {
                    $this->usuario->tokens()->delete();
                }

                if ($debeRetirarTurno) {
                    $generador->retirarTurno($this->usuario);
                }
            } else {
                $atributos['password'] = Hash::make($datos['dni']);
                $atributos['debe_actualizar_password'] = true;

                $this->usuario = User::create($atributos);
            }

            if ($esAdmin) {
                $this->usuario->syncRoles($roles);
                PapeletaActualizada::olvidarDestinatariosFijos();
            }
        });

        if ($this->usuario === null) {
            return;
        }

        session()->flash('mensaje', $this->usuario->wasRecentlyCreated ? 'Usuario creado.' : 'Usuario actualizado.');

        $this->redirectRoute($esAdmin ? 'usuarios-admin.index' : 'usuarios.index', navigate: false);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers de vista
    // ─────────────────────────────────────────────────────────────────

    /**
     * Sede que heredará el trabajador nuevo del Jefe de Área: la del jefe de
     * la unidad elegida. Antes de elegir unidad, se adelanta la sede propia
     * del Jefe de Área como vista previa (es lo más probable, y se corrige
     * sola en cuanto elige la unidad real).
     */
    protected function sedeHeredada(): ?string
    {
        if ($this->usuario !== null || ! $this->esArea() || $this->tipo !== 'trabajador') {
            return null;
        }

        return UnidadOrganica::with('jefe.sede')->find($this->unidadOrganicaId)?->jefe?->sede->nombre
            ?? Auth::user()?->sede?->nombre
            ?? 'Sin sede';
    }

    /** Cuenta con ese DNI (activa o desactivada). Solo aplica al crear como admin. */
    protected function buscarCuentaExistente(): ?User
    {
        if (! $this->esAdmin() || $this->usuario !== null || strlen($this->dni) !== 8) {
            return null;
        }

        return User::where('dni', $this->dni)->first();
    }

    public function render(): View
    {
        $unidades = match (true) {
            $this->esEquipo() => collect(),
            $this->esArea() => UnidadOrganica::orderBy('nombre')->whereIn('id', $this->idsDeArea())->pluck('nombre', 'id'),
            default => UnidadOrganica::orderBy('nombre')->pluck('nombre', 'id'),
        };

        return view('livewire.usuarios.usuario-form', [
            'cuentaExistente' => $this->buscarCuentaExistente(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'),
            'unidades' => $unidades,
            'roles' => $this->esAdmin()
                ? Role::orderBy('name')
                    ->when($this->usuario === null && ! $this->nuevoAdmin, fn ($q) => $q->where('name', '!=', 'admin'))
                    ->get()
                : collect(),
            'esAdminRol' => $this->esAdmin() && $this->incluyeAdmin(),
            'sedeHeredada' => $this->sedeHeredada(),
            'sedeDelCreador' => Auth::user()?->sede?->nombre,
        ]);
    }
}
