<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Actions\Usuario\CrearUsuarioAction;
use App\Actions\Usuario\EditarUsuarioAction;
use App\Exceptions\UsuarioException;
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
 * ÚNICO formulario de usuarios (alta y edición). Lo que cada quien puede
 * hacer depende de su ALCANCE, calculado en el servidor en mount() y
 * recalculado al guardar — nunca viene del cliente:
 *
 *  - admin   → todo. Cualquier unidad/sede, roles, contraseña, activo, turno.
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

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    public array $rolesSeleccionados = [];

    public bool $activo = true;

    /** Solo alcance area, al crear: trabajador | jefe_inmediato. */
    public string $tipo = 'trabajador';

    /** Modo «Nuevo administrador» (?tipo=admin). Fijado en mount(). */
    #[Locked]
    public bool $nuevoAdmin = false;

    public string $turno = '';

    public string $fechaAncla = '';

    public int $diasTrabajo = 6;

    public int $diasDescanso = 1;

    // ─────────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────────

    public function mount(?User $usuario = null): void
    {
        $existente = $usuario?->exists ? $usuario : null;

        $this->alcance = $this->resolverAlcance($existente);

        if ($existente) {
            $this->fechaAncla = now()->toDateString();
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

            $config = ConfiguracionTurno::where('user_id', $existente->id)->first();

            if ($config) {
                $this->turno = $config->turno;
                $this->fechaAncla = $config->fecha_ancla->toDateString();
                $this->diasTrabajo = $config->dias_trabajo;
                $this->diasDescanso = $config->dias_descanso;
            }

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
            $this->regimen = (string) (Auth::user()->regimen ?? '');

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
            $this->regimen = (string) (Auth::user()->regimen ?? '');
            $this->sedeId ??= Auth::user()->sede_id;
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

        if ($actor->hasRole('admin')) {
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

    protected function esSoloAdmin(): bool
    {
        $adminId = (int) Role::where('name', 'admin')->value('id');
        $roles = array_map('intval', $this->rolesSeleccionados);

        return $adminId > 0 && $roles !== [] && array_diff($roles, [$adminId]) === [];
    }

    protected function incluyeAdmin(): bool
    {
        $adminId = (int) Role::where('name', 'admin')->value('id');

        return $adminId > 0 && in_array($adminId, array_map('intval', $this->rolesSeleccionados), true);
    }

    protected function admiteConfiguracionTurno(): bool
    {
        if ($this->regimen !== '728') {
            return false;
        }

        // El Jefe Inmediato no toca turno desde aquí (tiene su propia pantalla).
        if ($this->esEquipo()) {
            return false;
        }

        // Al crear como Jefe Inmediato (alcance area), tampoco: él programa el suyo.
        if ($this->usuario === null && $this->esArea() && $this->tipo === 'jefe_inmediato') {
            return false;
        }

        if (! $this->usuario) {
            return true;
        }

        if ($this->cambioDeRegimen()) {
            return true;
        }

        return ! ConfiguracionTurno::where('user_id', $this->usuario->id)->exists();
    }

    protected function cambioDeRegimen(): bool
    {
        return $this->usuario !== null
            && ($this->usuario->regimen ?? '') !== ''
            && $this->regimen !== (string) $this->usuario->regimen;
    }

    public function updatedRegimen(): void
    {
        if ($this->cambioDeRegimen()) {
            $this->turno = '';
        }
    }

    /**
     * Régimen que debe coincidir con el del jefe inmediato de la unidad elegida.
     * - Jefe Inmediato: siempre el suyo propio.
     * - Jefe de Área creando un trabajador en una unidad que ya tiene jefe:
     *   el régimen de ese jefe.
     */
    protected function regimenEsperado(): ?string
    {
        if ($this->esEquipo()) {
            return Auth::user()->regimen;
        }

        if (! $this->esArea() || ($this->usuario === null && $this->tipo === 'jefe_inmediato')) {
            return null;
        }

        $unidad = UnidadOrganica::with('jefe')->find($this->unidadOrganicaId);

        if (! $unidad?->jefe || $unidad->jefe_id === $this->usuario?->id) {
            return null;
        }

        return $unidad->jefe->regimen;
    }

    // ─────────────────────────────────────────────────────────────────
    // Validación
    // ─────────────────────────────────────────────────────────────────

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

        // Jefe Inmediato "puro": puede activar/desactivar a sus propios
        // trabajadores al editarlos (antes solo lo podían admin y área).
        if ($this->esEquipo() && ! $creando) {
            $reglas['activo'] = ['boolean'];
        }

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

        $requiereTurno = $this->admiteConfiguracionTurno() && $this->turno !== '';

        $datos = $this->validate();

        if ($this->usuario === null && ! $this->esAdmin()) {
            $this->guardarComoJefe($datos);

            return;
        }

        if ($this->esEquipo()) {
            $this->guardarComoJefeInmediato($datos);

            return;
        }

        $this->guardarComoAdminOArea($datos, $requiereTurno, $generador);
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

        // Validar el régimen contra el jefe de la unidad.
        $esperado = $this->regimenEsperado();

        if ($esperado !== null && $this->regimen !== $esperado) {
            $this->addError('regimen', "El régimen debe coincidir con el del jefe inmediato de esa unidad (régimen {$esperado}).");

            return;
        }

        if ($esArea) {
            abort_unless($actor->can('crearEnUnidad', [User::class, (int) $this->unidadOrganicaId]), 403);
        } else {
            abort_unless($actor->can('puedeCrearAlgo', User::class), 403);
        }

        $conTurno = $this->admiteConfiguracionTurno() && $this->turno !== '';

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
                'turno' => $conTurno ? $this->turno : null,
                'fecha_ancla' => $conTurno ? $this->fechaAncla : null,
                'dias_trabajo' => $conTurno ? $this->diasTrabajo : null,
                'dias_descanso' => $conTurno ? $this->diasDescanso : null,
            ], $esArea);
        } catch (UsuarioException $e) {
            $this->addError('dni', $e->getMessage());

            return;
        }

        session()->flash('mensaje', "Usuario {$nuevo->nombre_completo} creado correctamente.");

        if ($nuevo->regimen === '728' && ! $conTurno && $this->tipo !== 'jefe_inmediato') {
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
        abort_unless(Auth::user()->can('editar', $this->usuario), 403);

        $activo = (bool) ($datos['activo'] ?? $this->usuario->activo);

        if ($this->usuario->activo && ! $activo) {
            $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar(Auth::user(), $this->usuario);

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

        $debeRetirarTurno = $this->usuario->activo && ! $activo;

        DB::transaction(function () use ($datos, $activo, $debeRetirarTurno) {
            app(EditarUsuarioAction::class)->ejecutar($this->usuario, [
                'name' => $datos['name'],
                'apellido' => $datos['apellido'],
                'email' => $datos['email'],
            ], false);

            $this->usuario->update(['activo' => $activo]);

            if (! $activo) {
                $this->usuario->tokens()->delete();
            }

            if ($debeRetirarTurno) {
                app(GeneradorTurnoMensualService::class)->retirarTurno($this->usuario);
            }
        });

        session()->flash('mensaje', "Usuario {$this->usuario->nombre_completo} actualizado.");

        $this->redirectRoute('usuarios.index', navigate: false);
    }

    /**
     * Admin (alta/edición completa) o Jefe de Área editando (sin roles ni contraseña).
     *
     * @param  array<string, mixed>  $datos
     */
    protected function guardarComoAdminOArea(array $datos, bool $requiereTurno, GeneradorTurnoMensualService $generador): void
    {
        $esAdmin = $this->esAdmin();
        $adminId = (int) Role::where('name', 'admin')->value('id');
        $trabajadorId = (int) Role::where('name', 'trabajador')->value('id');

        // Jefe de Área no modifica roles: conserva los actuales.
        $roles = $esAdmin
            ? array_map('intval', $datos['rolesSeleccionados'])
            : $this->usuario->roles->pluck('id')->map(fn ($id) => (int) $id)->all();

        $activo = ($esAdmin || $this->esArea())
            ? (bool) ($datos['activo'] ?? $this->usuario?->activo ?? true)
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

        // ── Guardia de desactivación (admin y jefe de área) ─────────

        if ($this->usuario?->activo && ! $activo) {
            $motivo = app(CambiarEstadoUsuarioAction::class)->motivoParaNoDesactivar(Auth::user(), $this->usuario);

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

        // ── Régimen (alcance area editando) ─────────────────────────

        $esperado = $this->regimenEsperado();

        if ($esperado !== null && $this->regimen !== $esperado) {
            $this->addError('regimen', "El régimen debe coincidir con el del jefe inmediato (régimen {$esperado}).");

            return;
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
            // Jefe de Área editando: sí puede mover de unidad y cambiar sede.
            $atributos['sede_id'] = $datos['sedeId'];
            $atributos['unidad_organica_id'] = $datos['unidadOrganicaId'];
        }

        $debeRetirarTurno = $this->usuario !== null
            && ($this->cambioDeRegimen() || ($this->usuario->activo && ! $activo));

        DB::transaction(function () use ($atributos, $datos, $requiereTurno, $generador, $debeRetirarTurno, $esAdmin, $activo, $roles) {
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
                \App\Events\PapeletaActualizada::olvidarDestinatariosFijos();
            }

            if ($requiereTurno && $activo) {
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

        $this->redirectRoute($esAdmin ? 'usuarios-admin.index' : 'usuarios.index', navigate: false);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers de vista
    // ─────────────────────────────────────────────────────────────────

    /** Sede que heredará el trabajador nuevo del Jefe de Área: la del jefe de la unidad elegida. */
    protected function sedeHeredada(): ?string
    {
        if ($this->usuario !== null || ! $this->esArea() || $this->tipo !== 'trabajador' || ! $this->unidadOrganicaId) {
            return null;
        }

        return UnidadOrganica::with('jefe.sede')->find($this->unidadOrganicaId)?->jefe?->sede?->nombre
            ?? Auth::user()->sede?->nombre
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
            'requiereTurno' => $this->usuario !== null && $this->admiteConfiguracionTurno(),
            'opcionesTurno' => $this->regimen
                ? ConfiguracionTurno::turnosValidosPara(new User(['regimen' => $this->regimen]))
                : [],
            'sedeHeredada' => $this->sedeHeredada(),
            'sedeDelCreador' => Auth::user()->sede?->nombre,
        ]);
    }
}
