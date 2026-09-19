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
                    ->select('trabajador_id'));

            if ($unidadIds->isNotEmpty()) {
                $q->orWhereIn('users.unidad_organica_id', $unidadIds);
            }
        });
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
     * ¿Es este usuario jefe inmediato del trabajador dado, ya sea de
     * forma automática (por unidad orgánica) o adicional (asignado a
     * mano)? Cualquiera de los dos habilita a decidir sus papeletas.
     */
    public function esJefeInmediatoDe(User $trabajador): bool
    {
        if ($trabajador->jefe_inmediato_id === $this->id) {
            return true;
        }

        // Con la relación ya cargada (with('trabajador.jefesInmediatosAdicionales'))
        // no hay query; si no, se memoiza por instancia para no repetirla
        // en listados/policies que llaman esto una vez por fila.
        if ($trabajador->relationLoaded('jefesInmediatosAdicionales')) {
            return $trabajador->jefesInmediatosAdicionales->contains('id', $this->id);
        }

        return $this->esAdicionalDe[$trabajador->id] ??= $trabajador->jefesInmediatosAdicionales()
            ->where('users.id', $this->id)
            ->exists();
    }

    /** @var array<int, bool> */
    private array $esAdicionalDe = [];

    public function turnos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Turno::class);
    }

    /**
     * Quién puede cargar/actualizar el ciclo de turno (mensual) de un
     * trabajador: Admin (rol Spatie), su Jefe Inmediato (automático o
     * adicional, ver esJefeInmediatoDe), su Jefe de Área explícito, o el
     * propio trabajador si es jefe de alguien (ver esJefeDeAlguien) —
     * un jefe no tiene, dentro del sistema, un jefe inmediato/de área
     * propio que le cargue el horario. "jefe" no es un rol de Spatie
     * (ver RoleSeeder) — por eso esto se resuelve por relación, no por
     * hasRole.
     */
    public function puedeGestionarTurnoDe(User $trabajador): bool
    {
        return $this->hasRole('admin')
            || ($this->id === $trabajador->id && $this->esJefeDeAlguien())
            || $this->esJefeInmediatoDe($trabajador)
            || $trabajador->jefe_area_id === $this->id;
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
            || $this->trabajadoresAdicionales()->exists();
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
