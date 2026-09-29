<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasPushSubscriptions;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'apellido',
        'dni',
        'email',
        'password',
        'debe_actualizar_password',
        'regimen',
        'activo',
        'sede_id',
        'jefe_inmediato_id',
        'jefe_area_id',
        'unidad_organica_id',
        'volumen_notificacion',
        'permite_gps',
        'permite_camara',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'debe_actualizar_password' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function sede(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Unidad orgánica (oficina/sub oficina/gerencia) a la que pertenece
     * este usuario dentro del organigrama.
     */
    public function unidadOrganica(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UnidadOrganica::class);
    }

    /**
     * Jefe inmediato asignado explícitamente. En la mayoría de los casos
     * este valor coincide con `jefe_id` de `unidadOrganica`, pero se guarda
     * de forma explícita en `users` porque las papeletas ya existentes
     * fotografían este dato y no deben cambiar si el organigrama se
     * reestructura después.
     */
    public function jefeInmediato(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_id');
    }

    /**
     * Jefe de área asignado explícitamente (jefe de la unidad padre de la
     * unidad de este usuario). Mismo motivo que jefeInmediato(): valor
     * explícito para no romper el historial si el árbol cambia.
     */
    public function jefeArea(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    /** Unidad orgánica que este usuario encabeza, si es jefe de alguna. */
    public function unidadesQueEncabeza(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'jefe_id');
    }

    /** Jefe de área = encabeza al menos una unidad orgánica del organigrama. */
    public function esJefeDeArea(): bool
    {
        return $this->unidadesQueEncabeza()->exists();
    }

    /**
     * Jefes inmediatos ADICIONALES de este usuario (cuando este usuario
     * es el trabajador), asignados a mano. Aparte de jefeInmediato()
     * (el automático, derivado de la unidad orgánica).
     */
    public function jefesInmediatosAdicionales(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'jefes_inmediatos_adicionales',
            'trabajador_id',
            'jefe_inmediato_id',
        )->withPivot('asignado_por_id')->withTimestamps();
    }

    /**
     * Trabajadores cuyo jefe_inmediato_id (columna explícita) es este
     * usuario: los que le llegan por ser jefe automático de una unidad
     * orgánica (ver UnidadOrganica::jefaturasDe). NO incluye a los
     * trabajadores de sub-unidades más abajo en el árbol (esos tienen
     * a otro jefe_inmediato_id): para eso ver EquipoDelJefeService,
     * que además suma los jefes de esas sub-unidades sin exponer a
     * todo el personal de cada una.
     */
    public function subordinadosInmediatos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'jefe_inmediato_id');
    }

    /**
     * Trabajadores que este usuario supervisa como jefe inmediato
     * ADICIONAL (asignado a mano), aparte de los que le llegan por
     * ser jefe automático de una unidad orgánica.
     */
    public function trabajadoresAdicionales(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'jefes_inmediatos_adicionales',
            'jefe_inmediato_id',
            'trabajador_id',
        )->withPivot('asignado_por_id')->withTimestamps();
    }

    /**
     * Fuente ÚNICA de "mi equipo": trabajadores cuyo jefe inmediato o de
     * área (columnas explícitas) soy yo, los asignados a mano como jefe
     * adicional y todos los de las unidades que encabezo (y sus
     * sub-unidades). Antes había tres definiciones distintas
     * (trabajadoresComoJefeInmediato, trabajadoresParaReportes y
     * EquipoDelJefeService) y un jefe adicional veía al trabajador en un
     * lado pero no en otro.
     */
    public function scopeEquipoDe(\Illuminate\Database\Eloquent\Builder $query, User $jefe): \Illuminate\Database\Eloquent\Builder
    {
        $unidadIds = $jefe->unidadesQueEncabeza
            ->flatMap(fn ($u) => collect([$u->id])->merge($u->descendantIds()))
            ->unique()
            ->values();

        return $query->where(function ($q) use ($jefe, $unidadIds) {
            $q->where('users.jefe_inmediato_id', $jefe->id)
                ->orWhere('users.jefe_area_id', $jefe->id)
                ->orWhereIn('users.id', \Illuminate\Support\Facades\DB::table('jefes_inmediatos_adicionales')
                    ->where('jefe_inmediato_id', $jefe->id)
                    ->select('trabajador_id'))
                ->orWhereIn('users.id', User::deLosTurnosQueCubre($jefe)->select('users.id'));

            if ($unidadIds->isNotEmpty()) {
                $q->orWhereIn('users.unidad_organica_id', $unidadIds);
            }
        });
    }

    /**
     * Trabajadores que caen bajo $jefe por ser JEFE DE TURNO (jefes_turno):
     * los de las unidades donde es jefe inmediato adicional, cuyo turno
     * configurado coincide con el turno configurado del propio $jefe
     * (ya no con un turno fijo elegido a mano en jefes_turno — ver
     * JefeTurno). Sin esto, los jefes de turno que no son `jefe_id` no
     * verían a nadie (users.jefe_inmediato_id apunta al jefe de la
     * unidad). Sin configuración de turno propia (programación día por día),
     * cubre al personal 728 de esas unidades.
     */
    public function scopeDeLosTurnosQueCubre(\Illuminate\Database\Eloquent\Builder $query, User $jefe): \Illuminate\Database\Eloquent\Builder
    {
        $unidadIds = \App\Models\JefeTurno::where('jefe_id', $jefe->id)->pluck('unidad_organica_id');

        $turnoDelJefe = \App\Models\ConfiguracionTurno::where('user_id', $jefe->id)->value('turno');

        if ($unidadIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        // Sin ciclo propio en configuraciones_turno (el jefe se programa
        // día por día, M-M-T-T-N-D): no hay un único turno con el cual
        // comparar, así que cubre al personal 728 de las unidades donde
        // es jefe inmediato. Quién puede aprobar cada papeleta sigue
        // resolviéndose aparte (UnidadOrganica::resolverJefesInmediatos).
        if ($turnoDelJefe === null) {
            return $query->whereIn('users.unidad_organica_id', $unidadIds)
                ->where('users.regimen', '728');
        }

        return $query->whereIn('users.unidad_organica_id', $unidadIds)
            ->whereIn('users.id', \Illuminate\Support\Facades\DB::table('configuraciones_turno')
                ->where('turno', $turnoDelJefe)
                ->select('user_id'));
    }

    /** Equipo del jefe como colección (para selects/buscadores). */
    public function equipo(): \Illuminate\Support\Collection
    {
        return User::equipoDe($this)->orderBy('name')->get();
    }

    /** @deprecated usar equipo() / User::equipoDe(); se mantiene por compatibilidad. */
    public function trabajadoresComoJefeInmediato(): \Illuminate\Support\Collection
    {
        return $this->equipo();
    }

    /** @deprecated usar equipo() / User::equipoDe(); se mantiene por compatibilidad. */
    public function trabajadoresParaReportes(): \Illuminate\Support\Collection
    {
        return $this->equipo();
    }

    /**
     * ¿Es este usuario el ÚNICO con rol 'rrhh' que sigue activo? Sin nadie
     * activo, RrhhHorarioService da a RRHH por "fuera de horario" y toda
     * papeleta aprobada por el jefe se autoriza sola (revisión post-hoc):
     * por eso no se permite desactivarlo ni quitarle el rol.
     */
    public function esUnicoRrhhActivo(): bool
    {
        return $this->activo
            && $this->hasRole('rrhh')
            && ! static::role('rrhh')->where('activo', true)->whereKeyNot($this->getKey())->exists();
    }

    /**
     * ¿Es este usuario jefe inmediato del trabajador dado?
     *
     * "Jefe inmediato" es UN solo tipo de jefe: todos los jefes inmediatos
     * de un trabajador tienen las mismas capacidades y privilegios (ver
     * sus papeletas y decidirlas, ver y editar al trabajador, programar
     * su turno, asignarle otros jefes). Lo único que varía es DE DÓNDE
     * viene la asignación, y eso solo describe su alcance, no su rol:
     *
     * - por la unidad orgánica (users.jefe_inmediato_id, derivado de
     *   unidades_organicas.jefe_id);
     * - por la unidad para el personal 728 (jefes_turno);
     * - a mano para ese trabajador (jefes_inmediatos_adicionales).
     *
     * Toda autorización de "jefe inmediato" debe pasar por aquí (o por
     * jefesInmediatos()) en vez de preguntar por cada origen a mano.
     */
    public function esJefeInmediatoDe(User $trabajador): bool
    {
        if ($trabajador->jefe_inmediato_id === $this->id) {
            return true;
        }

        // Con la relación ya cargada (with('trabajador.jefesInmediatosAdicionales'))
        // no hay query; si no, se memoiza por instancia para no repetirla
        // en listados/policies que llaman esto una vez por fila.
        $asignadoAMano = $trabajador->relationLoaded('jefesInmediatosAdicionales')
            ? $trabajador->jefesInmediatosAdicionales->contains('id', $this->id)
            : ($this->esAdicionalDe[$trabajador->id] ??= $trabajador->jefesInmediatosAdicionales()
                ->where('users.id', $this->id)
                ->exists());

        return $asignadoAMano || $this->esJefeDeTurnoDe($trabajador);
    }

    /**
     * TODOS los jefes inmediatos de este usuario (como trabajador), sin
     * distinguir su origen: el de su unidad, los de jefes_turno si es
     * 728 y los asignados a mano. Fuente única para mostrar "quiénes son
     * mis jefes" y para no volver a armar la lista según el origen.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function jefesInmediatos(): \Illuminate\Support\Collection
    {
        $ids = collect([$this->jefe_inmediato_id])
            ->merge($this->jefesInmediatosAdicionales()->get()->pluck('id'));

        if ($this->regimen === '728' && $this->unidad_organica_id !== null) {
            $ids = $ids->merge(JefeTurno::where('unidad_organica_id', $this->unidad_organica_id)->pluck('jefe_id'));
        }

        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }

    /** @var array<int, bool> */
    private array $esAdicionalDe = [];

    public function turnos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Turno::class);
    }

    /** Filas de jefes_turno donde este usuario es el jefe inmediato asignado. */
    public function turnosQueEncabeza(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(JefeTurno::class, 'jefe_id');
    }

    /**
     * ¿Es jefe inmediato de algún turno (MANANA/TARDE/NOCHE) de alguna
     * unidad (jefes_turno)? Si es así, no se le puede desactivar sin antes
     * reasignar esa fila a otro jefe — ver UsuarioAdminIndex::desactivar
     * y UsuarioAdminForm::guardar, que bloquean la desactivación con
     * este chequeo. Sin este guardrail, esa unidad-turno se quedaría
     * sin nadie que pueda decidir papeletas (DecisorDisponibleService
     * ya descarta a los inactivos).
     */
    public function esJefeInmediatoDeAlgunTurno(): bool
    {
        return $this->turnosQueEncabeza()->exists();
    }

    /**
     * Quién puede cargar/actualizar el turno (mensual) de un trabajador:
     *
     * - Admin (rol Spatie): a cualquiera.
     * - Su propia programación, si es jefe de alguien (ver esJefeDeAlguien):
     *   un jefe no tiene, dentro del sistema, un jefe inmediato/de área
     *   propio que le cargue el horario.
     * - Su Jefe de Área explícito (trabajador->jefe_area_id): también a los
     *   jefes inmediatos de su área.
     * - Un Jefe Inmediato (de cualquier origen, todos iguales): solo a los
     *   TRABAJADORES a su cargo, nunca a otro jefe inmediato. Un Jefe de
     *   Área (encabeza una unidad) conserva su alcance de siempre.
     *
     * "jefe" no es un rol de Spatie (ver RoleSeeder) — por eso esto se
     * resuelve por relación, no por hasRole.
     */
    public function puedeGestionarTurnoDe(User $trabajador): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->id === $trabajador->id) {
            return $this->esJefeDeAlguien();
        }

        // Entre jefes inmediatos de una misma unidad nadie programa a
        // otro: cada uno solo el suyo.
        if ($this->esJefeParDe($trabajador)) {
            return false;
        }

        if ($trabajador->jefe_area_id === $this->id) {
            return true;
        }

        // Un jefe inmediato no programa a otro jefe inmediato.
        if (! $this->esJefeDeArea() && $trabajador->esJefeDeAlguien()) {
            return false;
        }

        return $this->esJefeInmediatoDe($trabajador);
    }

    /**
     * ¿Comparte alguna unidad con $otro como jefe inmediato (jefe_id de
     * la unidad o jefe adicional en jefes_turno)? Entre pares no se
     * programan turnos.
     */
    public function esJefeParDe(User $otro): bool
    {
        if ($this->id === $otro->id) {
            return false;
        }

        $mias = $this->unidadIdsComoJefe();

        return $mias->isNotEmpty() && $otro->unidadIdsComoJefe()->intersect($mias)->isNotEmpty();
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function unidadIdsComoJefe(): \Illuminate\Support\Collection
    {
        return UnidadOrganica::where('jefe_id', $this->id)->pluck('id')
            ->merge(JefeTurno::where('jefe_id', $this->id)->pluck('unidad_organica_id'))
            ->unique();
    }

    /**
     * ¿Es jefe de turno (jefes_turno) de la unidad orgánica del
     * trabajador 728? Sin turno fijo: su turno sale de su propia
     * programación, así que cubre a todo el personal 728 de la unidad.
     */
    public function esJefeDeTurnoDe(User $trabajador): bool
    {
        return $trabajador->regimen === '728'
            && $trabajador->unidad_organica_id !== null
            && JefeTurno::where('jefe_id', $this->id)
                ->where('unidad_organica_id', $trabajador->unidad_organica_id)
                ->exists();
    }

    /**
     * ¿Este usuario es jefe de alguien, sea de forma automática (encabeza
     * una unidad orgánica o tiene trabajadores con jefe_inmediato_id
     * apuntando a él) o adicional (asignado a mano)? Mismo criterio que
     * UserPolicy::crearTrabajadorPropio() — se usa aquí para habilitar la
     * auto-gestión del propio turno: un jefe no tiene, por definición, un
     * jefe inmediato/de área propio dentro del sistema que le cargue el
     * horario, así que debe poder hacerlo él mismo.
     */
    public function esJefeDeAlguien(): bool
    {
        return $this->unidadesQueEncabeza()->exists()
            || User::where('jefe_inmediato_id', $this->id)->exists()
            || $this->trabajadoresAdicionales()->exists()
            || $this->turnosQueEncabeza()->exists();
    }

    public function papeletas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Papeleta::class, 'trabajador_id');
    }

    /** "Nombre Apellido" listo para reportes/planillas y listados. */
    protected function nombreCompleto(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => trim("{$this->name} {$this->apellido}"),
        );
    }
}
