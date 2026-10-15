<?php

namespace App\Livewire\Organigrama;

use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Actions\Usuario\CrearUsuarioAction;
use App\Actions\Usuario\EditarUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Support\ReglasDatosUsuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alta y edición de una persona DENTRO del organigrama, en un solo panel
 * lateral para admin y jefe de área.
 *
 * Se abre con `org-persona-abrir`:
 *  - {id}                  → editar a esa persona.
 *  - {unidadId?, tipo?}    → alta (unidad preseleccionada; tipo = trabajador | jefe_inmediato).
 *
 * No tiene reglas propias: reutiliza las mismas Actions y policies que los
 * formularios de página, así que un mismo dato se valida igual entre roles.
 *
 *  - Alta: CrearUsuarioAction (contraseña inicial = DNI, reingreso de cuentas
 *    desactivadas, jefe inmediato único por unidad) + UserPolicy::crearEnUnidad.
 *    Aquí el actor siempre es admin o jefe de área, así que se usa la vía «de área»
 *    (la unidad se elige explícitamente). El régimen debe coincidir con el del jefe de la
 *    unidad, igual que en CrearUsuarioRequest.
 *  - Edición: UserPolicy::editar. Cambiar de unidad pasa por MoverTrabajadorAction (mismo
 *    historial y reglas que arrastrar); nombres, correo y sede por EditarUsuarioAction;
 *    activar/desactivar (solo admin) por CambiarEstadoUsuarioAction.
 *  - DNI, régimen, roles, contraseña y turno NO se editan aquí: «Edición completa» (admin)
 *    o «Configurar turno».
 *
 * Avisa con `org-actualizado` {mensaje, personaId} para que el árbol se repinte, muestre el
 * aviso y revele (expanda, desplace y resalte) a la persona.
 */
class PersonaDrawer extends Component
{
    public bool $abierto = false;

    /** null = alta. */
    #[Locked]
    public ?int $personaId = null;

    /** Solo en el alta: 'trabajador' | 'jefe_inmediato'. */
    public string $tipo = 'trabajador';

    public string $name = '';

    public string $apellido = '';

    public string $dni = '';

    public string $email = '';

    public string $regimen = '';

    public ?int $unidadId = null;

    public ?int $sedeId = null;

    public bool $activo = true;

    public ?string $error = null;

    /** @var Collection<int, UnidadOrganica>|null */
    private ?Collection $permitidasMemo = null;

    #[On('org-persona-abrir')]
    public function abrir(?int $id = null, ?int $unidadId = null, string $tipo = 'trabajador'): void
    {
        $actor = $this->actor();

        $this->reset(['name', 'apellido', 'dni', 'email', 'regimen', 'unidadId', 'sedeId', 'error']);
        $this->resetValidation();
        $this->personaId = null;
        $this->activo = true;
        $this->tipo = $tipo === 'jefe_inmediato' ? 'jefe_inmediato' : 'trabajador';

        if ($id !== null) {
            $persona = User::findOrFail($id);
            $this->authorize('editar', $persona);

            $this->personaId = $persona->id;
            $this->name = $persona->name;
            $this->apellido = $persona->apellido;
            $this->dni = (string) $persona->dni;
            $this->email = $persona->email;
            $this->regimen = (string) $persona->regimen;
            $this->unidadId = $persona->unidad_organica_id;
            $this->sedeId = $persona->sede_id;
            $this->activo = (bool) $persona->activo;
        } elseif ($unidadId !== null && $this->unidadesPermitidas($actor)->contains('id', $unidadId)) {
            $this->unidadId = $unidadId;
            $this->sugerirRegimenDeLaUnidad();
        }

        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function elegirTipo(string $tipo): void
    {
        if ($this->personaId !== null) {
            return;
        }

        $this->tipo = $tipo === 'jefe_inmediato' ? 'jefe_inmediato' : 'trabajador';
        $this->resetValidation(['sedeId', 'regimen']);
    }

    /** Al elegir unidad en el alta de un trabajador se sugiere el régimen de su jefe (debe coincidir). */
    public function updatedUnidadId(): void
    {
        if ($this->personaId === null) {
            $this->sugerirRegimenDeLaUnidad();
        }
    }

    public function guardar(): void
    {
        $actor = $this->actor();
        $this->error = null;

        if ($this->personaId === null) {
            $this->crear($actor);
        } else {
            $this->editar($actor);
        }
    }

    private function crear(User $actor): void
    {
        $permitidas = $this->unidadesPermitidas($actor);
        $existenteInactivo = User::where('dni', $this->dni)->where('activo', false)->first();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'dni' => ReglasDatosUsuario::dni(soloActivos: true),
            'email' => ReglasDatosUsuario::email($existenteInactivo?->id),
            'regimen' => ['required', Rule::in(['276', '728'])],
            'tipo' => ['required', Rule::in(['trabajador', 'jefe_inmediato'])],
            'unidadId' => ['required', Rule::in($permitidas->pluck('id')->all())],
            'sedeId' => $this->tipo === 'jefe_inmediato' ? ['required', 'exists:sedes,id'] : ['nullable'],
        ]);

        $this->authorize('crearEnUnidad', [User::class, (int) $this->unidadId]);

        $destino = UnidadOrganica::with('jefe')->find($this->unidadId);
        $regimenDelJefe = $destino?->jefe?->regimen;

        if ($this->tipo === 'trabajador' && $regimenDelJefe && $regimenDelJefe !== $this->regimen) {
            $this->addError('regimen', "El régimen debe coincidir con el de su jefe inmediato (régimen {$regimenDelJefe}).");

            return;
        }

        try {
            $nuevo = app(CrearUsuarioAction::class)->ejecutar($actor, [
                'name' => $this->name,
                'apellido' => $this->apellido,
                'dni' => $this->dni,
                'email' => $this->email,
                'regimen' => $this->regimen,
                'sede_id' => $this->tipo === 'jefe_inmediato' ? $this->sedeId : null,
                'unidad_organica_id' => $this->unidadId,
                'tipo' => $this->tipo,
                'turno' => null,
                'fecha_ancla' => null,
                'dias_trabajo' => null,
                'dias_descanso' => null,
            ], true);
        } catch (UsuarioException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $mensaje = $this->tipo === 'jefe_inmediato'
            ? "Se creó a {$nuevo->nombre_completo} como jefe inmediato de {$destino->nombre}."
            : "Se creó a {$nuevo->nombre_completo}.";

        // Un 728 se crea sin turno, pero hasta que se lo programen no puede crear papeletas.
        if ($this->tipo === 'trabajador' && $nuevo->regimen === '728') {
            $mensaje .= ' Es 728 y aún no tiene turno: cárgalo en la programación de turnos.';
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: $mensaje, personaId: $nuevo->id);
    }

    private function editar(User $actor): void
    {
        $persona = User::find($this->personaId);

        if (! $persona) {
            $this->error = 'La persona ya no existe.';

            return;
        }

        $this->authorize('editar', $persona);

        $esAdmin = $actor->hasRole('admin');
        $encabeza = $persona->unidadesQueEncabeza()->exists();
        $cambiaUnidad = ! $encabeza
            && $this->unidadId !== null
            && (int) $this->unidadId !== (int) $persona->unidad_organica_id;

        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ReglasDatosUsuario::email($persona->id),
            // Mismo criterio que EditarUsuarioRequest: el jefe de área nunca deja a alguien sin sede.
            'sedeId' => $esAdmin ? ['nullable', 'exists:sedes,id'] : ['required', 'exists:sedes,id'],
            'unidadId' => ['nullable', 'exists:unidad_organicas,id'],
            'activo' => ['boolean'],
        ];

        if ($cambiaUnidad) {
            $reglas['unidadId'][] = Rule::in($this->unidadesPermitidas($actor)->pluck('id')->all());
        }

        $this->validate($reglas);

        // Solo el admin activa/desactiva (EditarUsuarioRequest tampoco lo admite para los jefes).
        $activo = $esAdmin ? $this->activo : (bool) $persona->activo;
        $estado = app(CambiarEstadoUsuarioAction::class);

        // Validar la desactivación ANTES de tocar nada: si se rechaza, no queda a medias.
        if ($persona->activo && ! $activo) {
            $motivo = $estado->motivoParaNoDesactivar($actor, $persona);

            if ($motivo !== null) {
                $this->error = $motivo;

                return;
            }

            // Jefe con gente a cargo: el reemplazo se decide en su propio panel.
            if ($estado->jefaturasPorResolver($persona)->isNotEmpty()) {
                $this->abierto = false;
                $this->dispatch('usuario-baja-abrir', id: $persona->id);

                return;
            }
        }

        try {
            // Primero la unidad: es la que puede rechazarse por reglas de negocio.
            if ($cambiaUnidad) {
                $mover = app(MoverTrabajadorAction::class);

                if ($persona->unidad_organica_id === null) {
                    // Sin unidad de origen no hay «movimiento»: se asigna directo,
                    // pero igual el destino debe tener jefe inmediato.
                    $mover->exigirJefeInmediato(UnidadOrganica::findOrFail($this->unidadId));
                    $persona->unidad_organica_id = $this->unidadId;
                    $persona->save(); // UserObserver recalcula jefe inmediato y de área.
                } else {
                    $mover->ejecutar($actor, $persona->id, (int) $this->unidadId, (int) $persona->unidad_organica_id, true, false);
                }

                $persona->refresh();
            }

            app(EditarUsuarioAction::class)->ejecutar($persona, [
                'name' => $this->name,
                'apellido' => $this->apellido,
                'email' => $this->email,
                'sede_id' => $this->sedeId,
                'unidad_organica_id' => $persona->unidad_organica_id,
            ], true);

            // EditarUsuarioAction usa sede_id ?? la actual (pensado para el jefe de área, que nunca
            // envía null porque la tiene obligatoria): para el admin, que sí puede dejarla vacía,
            // se fija aparte para que un null explícito (quitarle la sede) sí se guarde.
            if ($esAdmin) {
                $persona->forceFill(['sede_id' => $this->sedeId])->save();
            }

            if ($persona->activo && ! $activo) {
                $estado->desactivar($actor, $persona);
            } elseif (! $persona->activo && $activo) {
                $estado->reactivar($actor, $persona);
            }
        } catch (UsuarioException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->abierto = false;
        $this->dispatch('org-actualizado', mensaje: "Datos de {$persona->nombre_completo} actualizados.", personaId: $persona->id);
    }

    public function render(): View
    {
        if (! $this->abierto) {
            return view('livewire.organigrama.persona-drawer', ['abierto' => false]);
        }

        $actor = $this->actor();
        $permitidas = $this->unidadesPermitidas($actor);
        $persona = $this->personaId
            ? User::with(['jefeInmediato', 'sede', 'unidadOrganica'])->find($this->personaId)
            : null;

        $destino = $this->unidadId ? UnidadOrganica::with('jefe.sede')->find($this->unidadId) : null;

        $unidades = $permitidas
            ->map(fn (UnidadOrganica $u) => ['id' => (int) $u->id, 'label' => $u->nombre, 'hint' => $u->jefe?->nombre_completo ?? 'sin jefe'])
            ->values();

        // La unidad actual de la persona se muestra siempre, aunque ya no esté entre las elegibles (p. ej. desactivada).
        if ($persona?->unidadOrganica && ! $unidades->contains('id', (int) $persona->unidad_organica_id)) {
            $unidades->push(['id' => (int) $persona->unidad_organica_id, 'label' => $persona->unidadOrganica->nombre, 'hint' => null]);
        }

        return view('livewire.organigrama.persona-drawer', [
            'abierto' => true,
            'creando' => $this->personaId === null,
            'persona' => $persona,
            'esAdmin' => $actor->hasRole('admin'),
            'destino' => $destino,
            'unidades' => $unidades->all(),
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'label' => $s->nombre, 'hint' => null])->all(),
            'encabeza' => $persona ? $persona->unidadesQueEncabeza()->orderBy('nombre')->pluck('nombre') : collect(),
            // Sede que heredará un trabajador nuevo (ver CrearUsuarioAction::resolverSede): la del jefe de la unidad; si no, la del creador.
            'sedeHeredada' => $destino ? ($destino->jefe?->sede?->nombre ?? $actor->sede?->nombre ?? 'Sin sede') : null,
        ]);
    }

    private function sugerirRegimenDeLaUnidad(): void
    {
        if ($this->tipo !== 'trabajador' || $this->regimen !== '' || $this->unidadId === null) {
            return;
        }

        $regimen = UnidadOrganica::with('jefe')->find($this->unidadId)?->jefe?->regimen;

        if ($regimen) {
            $this->regimen = (string) $regimen;
        }
    }

    private function actor(): User
    {
        /** @var User|null $usuario */
        $usuario = auth()->user();

        // La autorización se repite aquí: las llamadas de Livewire no re-evalúan el middleware de la ruta.
        abort_unless($usuario && ($usuario->hasRole('admin') || $usuario->esJefeDeArea()), 403);

        return $usuario;
    }

    /**
     * Unidades activas donde $actor puede ubicar gente: todas (admin) o su área
     * (la que encabeza + sub-unidades), igual que CrearUsuarioRequest::unidadesDisponibles().
     *
     * @return Collection<int, UnidadOrganica>
     */
    private function unidadesPermitidas(User $actor): Collection
    {
        if ($this->permitidasMemo !== null) {
            return $this->permitidasMemo;
        }

        $consulta = UnidadOrganica::with('jefe.sede')->where('activo', true)->orderBy('nombre');

        if (! $actor->hasRole('admin')) {
            $ids = collect();

            foreach ($actor->unidadesDeArea as $unidad) {
                $ids->push($unidad->id);
                $ids = $ids->merge($unidad->descendantIds());
            }

            $consulta->whereIn('id', $ids->unique()->values()->all());
        }

        return $this->permitidasMemo = $consulta->get();
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombres',
            'apellido' => 'apellidos',
            'dni' => 'DNI',
            'email' => 'correo',
            'regimen' => 'régimen',
            'unidadId' => 'unidad',
            'sedeId' => 'sede',
        ];
    }

    protected function messages(): array
    {
        return [
            'dni.digits' => 'El DNI debe tener 8 dígitos.',
            'dni.unique' => 'Ya existe un usuario activo con ese DNI.',
            'email.unique' => 'Ese correo ya está en uso por otro usuario.',
            'unidadId.required' => 'Elige la unidad.',
            'unidadId.in' => 'Esa unidad no pertenece a tu área.',
            'sedeId.required' => 'Elige la sede: es necesaria para los cierres.',
        ];
    }
}