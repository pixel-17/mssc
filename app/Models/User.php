<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read string $nombre_completo
 */
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
     *  @var list<string> 
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
     *  @var list<string> 
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
     *  @var list<string> 
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

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<UnidadOrganica, $this>
     */
    public function unidadOrganica(): BelongsTo
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
    public function jefeInmediato(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_inmediato_id');
    }

    /**
     * Jefe de área asignado explícitamente (jefe de la unidad padre de la
     * unidad de este usuario). Mismo motivo que jefeInmediato(): valor
     * explícito para no romper el historial si el árbol cambia.
     */
    public function jefeArea(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_area_id');
    }

    /** Unidad orgánica que este usuario encabeza, si es jefe de alguna. */
    public function unidadesQueEncabeza(): HasMany
    {
        return $this->hasMany(UnidadOrganica::class, 'jefe_id');
    }

    /**
     * Unidades que este usuario encabeza Y que tienen sub-unidades: solo
     * esas lo hacen Jefe de Área. Quien encabeza una unidad hoja (sin
     * sub-oficinas) es Jefe Inmediato "puro": su jefe_id lo pone como
     * jefe de ESA oficina, no le da mando sobre otras.
     */
    public function unidadesDeArea(): HasMany
    {
        return $this->unidadesQueEncabeza()->whereHas('hijos');
    }

    /** Jefe titular de alguna unidad (hoja o no): jefe inmediato O de área. Usar para blindar contra ediciones/vínculos de pares. */
    public function esJefeTitular(): bool
    {
        return $this->unidadesQueEncabeza()->exists();
    }

    /** Tipos de usuario que se muestran en pantalla (clave => etiqueta). */
    public const TIPOS_USUARIO = [
        'admin' => 'Administrador',
        'rrhh' => 'RRHH',
        'jefe_area' => 'Jefe de área',
        'jefe_inmediato' => 'Jefe inmediato',
        'trabajador' => 'Trabajador',
    ];

    private ?string $tipoUsuarioMemo = null;

    /**
     * Clave del tipo de usuario para mostrarlo en pantalla (la más alta que
     * aplique): admin > rrhh > jefe de área > jefe inmediato > trabajador.
     * Es solo una etiqueta visual; los permisos siguen en policies y roles.
     * Se memoiza por instancia (hasta 3 consultas la primera vez).
     *
     * @return 'admin'|'rrhh'|'jefe_area'|'jefe_inmediato'|'trabajador'
     */
    public function tipoUsuario(): string
    {
        return $this->tipoUsuarioMemo ??= match (true) {
            $this->hasRole('admin') => 'admin',
            $this->hasRole('rrhh') => 'rrhh',
            $this->esJefeDeArea() => 'jefe_area',
            $this->esJefeTitular() => 'jefe_inmediato',
            default => 'trabajador',
        };
    }

    public function etiquetaTipoUsuario(): string
    {
        return self::TIPOS_USUARIO[$this->tipoUsuario()];
    }

    /** Jefe de área = encabeza al menos una unidad que tiene sub-unidades. */
    public function esJefeDeArea(): bool
    {
        return $this->unidadesDeArea()->exists();
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
    public function subordinadosInmediatos(): HasMany
    {
        return $this->hasMany(User::class, 'jefe_inmediato_id');
    }

    /**
     * ¿Es jefe de equipo (inmediato o de área)? NO incluye al admin:
     * el admin administra cuentas, no gestiona trabajadores ni papeletas.
     * Es la única fuente para menú, dashboard, reportes y bandeja de jefe.
     */
    public function esJefeDeEquipo(): bool
    {
        if ($this->hasRole('admin')) {
            return false;
        }

        return $this->unidadesQueEncabeza()->exists()
            || static::where('jefe_inmediato_id', $this->id)->exists();
    }

    /**
     * Usuarios que son jefe de alguien: encabezan una unidad o figuran como
     * jefe inmediato de otro usuario. Misma regla que esJefeDeAlguien(), pero
     * resuelta en SQL para filtrar listas enteras sin 2 consultas por fila.
     */
    public function scopeQueEsJefe(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('unidadesQueEncabeza')
            ->orWhereIn('users.id', User::query()
                ->select('jefe_inmediato_id')
                ->whereNotNull('jefe_inmediato_id')));
    }

    /**
     * Fuente ÚNICA de "mi equipo": trabajadores cuyo jefe inmediato o de
     * área (columnas explícitas) soy yo y todos los de las unidades que
     * encabezo (y sus sub-unidades).
     */
    public function scopeEquipoDe(Builder $query, User $jefe): Builder
    {
        $unidadIds = $jefe->unidadesQueEncabeza
            ->flatMap(fn ($u) => collect([$u->id])->merge($u->descendantIds()))
            ->unique()
            ->values();

        return $query->where(function ($q) use ($jefe, $unidadIds) {
            $q->where('users.jefe_inmediato_id', $jefe->id)
                ->orWhere('users.jefe_area_id', $jefe->id);

            if ($unidadIds->isNotEmpty()) {
                $q->orWhereIn('users.unidad_organica_id', $unidadIds);
            }
        });
    }

    /** Equipo del jefe como colección (para selects/buscadores). */
    public function equipo(): Collection
    {
        return User::equipoDe($this)->orderBy('name')->get();
    }

    /** @deprecated usar equipo() / User::equipoDe(); se mantiene por compatibilidad. */
    public function trabajadoresComoJefeInmediato(): Collection
    {
        return $this->equipo();
    }

    /** @deprecated usar equipo() / User::equipoDe(); se mantiene por compatibilidad. */
    public function trabajadoresParaReportes(): Collection
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
     * ¿Es este usuario el ÚNICO con rol 'admin' que sigue activo? Sin
     * ningún admin activo nadie podría gestionar el sistema, así que no
     * se permite desactivarlo ni quitarle el rol.
     */
    public function esUnicoAdminActivo(): bool
    {
        return $this->activo
            && $this->hasRole('admin')
            && ! static::role('admin')->where('activo', true)->whereKeyNot($this->getKey())->exists();
    }

    /**
     * ¿Es este usuario el jefe inmediato del trabajador dado? Hay UN solo jefe
     * inmediato por trabajador (users.jefe_inmediato_id, derivado de
     * unidades_organicas.jefe_id), sin importar régimen ni turno.
     *
     * Toda autorización de "jefe inmediato" debe pasar por aquí.
     */
    public function esJefeInmediatoDe(User $trabajador): bool
    {
        return $trabajador->jefe_inmediato_id !== null
            && (int) $trabajador->jefe_inmediato_id === (int) $this->id;
    }

    /**
     * Colección con el jefe inmediato de este usuario (0 o 1). Se mantiene
     * como colección para las vistas que listan "mis jefes".
     *
     * @return Collection<int, User>
     */
    public function jefesInmediatos(): Collection
    {
        return $this->jefe_inmediato_id === null
            ? collect()
            : User::whereKey($this->jefe_inmediato_id)->get();
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
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
     * la unidad)? Entre pares no se
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

    /** @return Collection<int, int> */
    private function unidadIdsComoJefe(): Collection
    {
        return UnidadOrganica::where('jefe_id', $this->id)->pluck('id');
    }

    /**
     * ¿Este usuario es jefe de alguien, sea (encabeza una unidad orgánica o tiene
     * trabajadores con jefe_inmediato_id apuntando a él)? Mismo criterio que
     * UserPolicy::crearTrabajadorPropio() — se usa aquí para habilitar la
     * auto-gestión del propio turno: un jefe no tiene, por definición, un
     * jefe inmediato/de área propio dentro del sistema que le cargue el
     * horario, así que debe poder hacerlo él mismo.
     */
    public function esJefeDeAlguien(): bool
    {
        return $this->unidadesQueEncabeza()->exists()
            || User::where('jefe_inmediato_id', $this->id)->exists();
    }

    public function papeletas(): HasMany
    {
        return $this->hasMany(Papeleta::class, 'trabajador_id');
    }

    /** "Nombre Apellido" listo para reportes/planillas y listados. */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->name} {$this->apellido}"),
        );
    }
}
