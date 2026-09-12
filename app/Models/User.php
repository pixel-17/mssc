<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
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
     * El panel /admin de Filament es solo para admin — los catálogos y
     * la gestión de usuarios viven ahí. RRHH, jefes y trabajadores usan
     * sus propias bandejas en Blade + Livewire, nunca este panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }

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
        'regimen',
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
     * Todos los trabajadores que este usuario ve como Jefe Inmediato:
     * los automáticos (jefe_inmediato_id de la unidad orgánica) más
     * los adicionales asignados a mano. Para bandejas y visibilidad.
     */
    public function trabajadoresComoJefeInmediato(): \Illuminate\Support\Collection
    {
        return User::where('jefe_inmediato_id', $this->id)
            ->get()
            ->merge($this->trabajadoresAdicionales)
            ->unique('id')
            ->values();
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

        return $trabajador->jefesInmediatosAdicionales()
            ->where('users.id', $this->id)
            ->exists();
    }

    public function turnos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Turno::class);
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
