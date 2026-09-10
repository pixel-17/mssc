<?php

namespace App\Policies;

use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;

/**
 * Autorización del flujo operativo de papeletas para los 4 roles
 * activos. admin NUNCA decide papeletas — solo administra catálogos
 * (ver Admin\* Controllers/Resources); su rol no aparece aquí a
 * propósito.
 *
 * Jefe Inmediato y Jefe de Área comparten el mismo modelo User y la
 * misma Policy: se distinguen por CUÁL columna de la papeleta los
 * referencia (jefe_inmediato_id vs jefe_area_id), no por una tabla
 * o rol de permiso separado.
 */
class PapeletaPolicy
{
    public function view(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            || $papeleta->trabajador_id === $user->id
            || $papeleta->jefe_inmediato_id === $user->id
            || $papeleta->jefe_area_id === $user->id;
    }

    public function crear(User $user): bool
    {
        return $user->hasRole('trabajador');
    }

    public function cancelar(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->trabajador_id === $user->id
            && $papeleta->estado->equals(PendienteJefe::class);
    }

    /**
     * Cubre aprobar/rechazar/observar como jefe. El Jefe Inmediato
     * decide mientras la papeleta no haya escalado; una vez escalada
     * (escalado_jefe_area_at no nulo), solo el Jefe de Área puede
     * actuar — el inmediato ya perdió la ventana.
     */
    public function decidirComoJefe(User $user, Papeleta $papeleta): bool
    {
        if (! $papeleta->estado->equals(PendienteJefe::class)) {
            return false;
        }

        if ($papeleta->escalado_jefe_area_at !== null) {
            return $papeleta->jefe_area_id === $user->id;
        }

        return $papeleta->jefe_inmediato_id === $user->id;
    }

    /**
     * La observación de RRHH solo la reconoce el Jefe Inmediato
     * original — nunca el trabajador, nunca el Jefe de Área.
     */
    public function reconocerObservacionRrhh(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->estado->equals(ObservadaPorRrhh::class)
            && $papeleta->jefe_inmediato_id === $user->id;
    }

    public function decidirComoRrhh(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            && $papeleta->estado->equals(PendienteRrhh::class);
    }

    /**
     * Revisión post-hoc (Paso 4): mismo permiso base de RRHH, la
     * restricción real (que autorizado_con_rrhh_fuera_horario sea
     * true y no esté ya revisada) vive en el propio Action.
     */
    public function revisarPosthoc(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh');
    }

    public function marcarRetorno(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->trabajador_id === $user->id;
    }

    /**
     * Falla de conectividad (Paso 5): el jefe inmediato es quien
     * marca el retorno manual en nombre del trabajador.
     */
    public function marcarRetornoManual(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->jefe_inmediato_id === $user->id;
    }

    /**
     * Comisión de Servicio sin retorno físico (Paso 5): visto bueno
     * humano del jefe inmediato o de RRHH.
     */
    public function cerrarSinRetorno(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->jefe_inmediato_id === $user->id || $user->hasRole('rrhh');
    }

    /**
     * "Abandono + sustento vencido simultáneos -> gana el abandono"
     * (Paso 5): mismo criterio que cerrarSinRetorno, jefe o RRHH.
     */
    public function marcarAbandono(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->jefe_inmediato_id === $user->id || $user->hasRole('rrhh');
    }

    /**
     * Visto bueno humano sobre un sustento ya presentado (Paso 8):
     * jefe inmediato o RRHH de la papeleta dueña del sustento.
     */
    public function revisarSustento(User $user, \App\Models\Sustento $sustento): bool
    {
        return $sustento->papeleta->jefe_inmediato_id === $user->id || $user->hasRole('rrhh');
    }
}
