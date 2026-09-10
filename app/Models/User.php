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
        'regimen',
        'sede_id',
        'jefe_inmediato_id',
        'jefe_area_id',
        'unidad_organica_id',
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
