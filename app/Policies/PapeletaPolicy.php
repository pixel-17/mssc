<?php

namespace App\Policies;

use App\Actions\Papeleta\RrhhHorarioService;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\Finalizada;
use App\States\Papeleta\ObservadaPorJefe;
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
 *
 * Cada trabajador tiene un solo jefe inmediato: el de su unidad
 * orgánica (users.jefe_inmediato_id, ver User::esJefeInmediatoDe()).
 *
 * Dos fuentes de verdad distintas a propósito:
 * - VISIBILIDAD (view/verArchivos/verSustento): EN VIVO, contra el
 *   organigrama actual del trabajador (ver esJefeVigenteDe()). Si
 *   cambia de jefe, el jefe anterior deja de ver sus papeletas
 *   (aunque las haya resuelto él) y el jefe nuevo las ve todas.
 * - DECISIÓN/ACCIÓN (decidirComoJefe, responderPosthoc, etc.): contra
 *   lo FOTOGRAFIADO en la papeleta (jefe_inmediato_id, jefe_area_id,
 *   resuelto_por_jefe_id), que es historial y no se toca.
 */
class PapeletaPolicy
{
    /**
     * Regla transversal: nadie decide, revisa ni cierra su PROPIA
     * papeleta (Actions\Papeleta\Concerns\ExigeDecisorAjeno la repite
     * en la capa de dominio). Las habilidades que se resuelven por rol
     * (RRHH) necesitan esta guarda explícita; las de jefe ya excluyen a
     * la propia persona porque un jefe no es su propio jefe inmediato
     * (ver UnidadOrganica::jefaturasDe). Se mantiene igual como defensa
     * en profundidad.
     */
    private function esPropia(User $user, Papeleta $papeleta): bool
    {
        return (int) $papeleta->trabajador_id === (int) $user->id;
    }

    /**
     * ¿$user es, AHORA MISMO, el jefe inmediato o de área del trabajador
     * de esta papeleta (o uno de los jefes inmediatos de su área)? A
     * diferencia de tieneComoJefeInmediatoA() / jefe_area_id (que comparan
     * contra lo FOTOGRAFIADO en la papeleta al crearla o reasignarla),
     * esto consulta el organigrama VIGENTE con User::equipoDe() — la
     * misma fuente única que usan los reportes/dashboards.
     *
     * Esto es solo para VISIBILIDAD (ver papeleta, archivos, sustento):
     * nunca para decidir ni modificar nada, por eso no se usa en
     * decidirComoJefe(), responderPosthoc(), etc. — esas acciones deben
     * seguir dependiendo de lo fotografiado, porque son historial y no se
     * puede alterar quién decidió qué.
     *
     * Efecto práctico: si un trabajador cambia de jefe, el jefe anterior
     * deja de ver sus papeletas (abiertas o ya cerradas, las haya resuelto
     * él o no) y el jefe nuevo pasa a verlas todas de inmediato.
     */
    private function esJefeVigenteDe(User $user, Papeleta $papeleta): bool
    {
        return User::equipoDe($user)->whereKey($papeleta->trabajador_id)->exists();
    }

    public function view(User $user, Papeleta $papeleta): bool
    {
        // El admin ve todas, en cualquier estado. Solo lectura: las acciones
        // siguen siendo de jefe y RRHH (cada una tiene su propio método).
        return $user->hasRole('admin')
            || $user->hasRole('rrhh')
            || $papeleta->trabajador_id === $user->id
            || $this->esJefeVigenteDe($user, $papeleta);
    }

    /**
     * Archivos de la papeleta (adjunto inicial, foto del retorno, adjuntos de
     * observación y post-hoc). Mismos actores que `view` salvo el admin: él ve
     * la papeleta en solo lectura, pero sus archivos pueden ser documentos
     * médicos y no le corresponden.
     */
    public function verArchivos(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            || $papeleta->trabajador_id === $user->id
            || $this->esJefeVigenteDe($user, $papeleta);
    }

    public function crear(User $user): bool
    {
        // El admin no marca papeletas, aunque se le haya asignado también el rol trabajador.
        return $user->hasRole('trabajador') && ! $user->hasRole('admin');
    }

    /**
     * El trabajador cancela hasta antes de AUTORIZADA_Y_CORRIENDO. Mientras
     * la papeleta espera a RRHH solo se puede si RRHH está en horario (ver
     * CancelarPapeletaAction).
     */
    public function cancelar(User $user, Papeleta $papeleta): bool
    {
        if ($papeleta->trabajador_id !== $user->id) {
            return false;
        }

        if ($papeleta->estado->equals(PendienteJefe::class, ObservadaPorJefe::class)) {
            return true;
        }

        return $papeleta->estado->equals(PendienteRrhh::class, ObservadaPorRrhh::class)
            && app(RrhhHorarioService::class)->estaEnHorarioAhora();
    }

    /** El trabajador responde por escrito (y con adjunto si se lo exigieron) a la observación del jefe. */
    public function subsanarObservacion(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->trabajador_id === $user->id
            && $papeleta->estado->equals(ObservadaPorJefe::class);
    }

    /**
     * Cubre aprobar/rechazar/observar como jefe. Decidir es SIEMPRE
     * responsabilidad del Jefe Inmediato: no escala a nadie más por
     * inacción (ni al Jefe de Área ni a nadie), la papeleta simplemente
     * espera hasta que el job de vencimiento la marca Vencida al
     * terminar el turno/día.
     *
     * También puede rechazar una papeleta que él mismo observó
     * (OBSERVADA_POR_JEFE) sin esperar la respuesta; aprobar u observar
     * de nuevo solo procede desde PENDIENTE_JEFE (lo vigilan
     * AprobarJefeAction y ObservarJefeAction).
     */
    public function decidirComoJefe(User $user, Papeleta $papeleta): bool
    {
        if ($this->esPropia($user, $papeleta)) {
            return false;
        }

        // Tras una observación de RRHH el jefe decide directamente (aprobar, observar o rechazar).
        if (! $papeleta->estado->equals(PendienteJefe::class, ObservadaPorJefe::class, ObservadaPorRrhh::class)) {
            return false;
        }

        return $papeleta->tieneComoJefeInmediatoA($user);
    }

    /**
     * La observación de RRHH solo la reconoce alguno de los Jefes
     * Inmediatos del trabajador — nunca el trabajador, nunca el Jefe
     * de Área.
     */
    public function reconocerObservacionRrhh(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->estado->equals(ObservadaPorRrhh::class)
            && ! $this->esPropia($user, $papeleta)
            && $papeleta->tieneComoJefeInmediatoA($user);
    }

    /**
     * Responder la observación post-hoc de RRHH: solo el MISMO jefe que
     * autorizó la papeleta (ver Papeleta::puedeResponderPosthoc()).
     */
    public function responderPosthoc(User $user, Papeleta $papeleta): bool
    {
        return $papeleta->puedeResponderPosthoc($user);
    }

    public function decidirComoRrhh(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            && ! $this->esPropia($user, $papeleta)
            && $papeleta->estado->equals(PendienteRrhh::class);
    }

    /**
     * Revisión post-hoc (Paso 4): mismo permiso base de RRHH, la
     * restricción real (que autorizado_con_rrhh_fuera_horario sea
     * true y no esté ya revisada) vive en el propio Action.
     */
    public function revisarPosthoc(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh') && ! $this->esPropia($user, $papeleta);
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
        return ! $this->esPropia($user, $papeleta)
            && $papeleta->tieneComoJefeInmediatoA($user);
    }

    /**
     * Visto bueno humano sobre la justificación presentada (Paso 8): solo
     * RRHH, y nunca sobre su propia papeleta. El jefe inmediato no la revisa.
     */
    public function revisarSustento(User $user, Sustento $sustento): bool
    {
        return ! $this->esPropia($user, $sustento->papeleta)
            && $user->hasRole('rrhh');
    }

    /**
     * Ver/descargar el archivo adjunto de un sustento: el propio
     * trabajador dueño, RR. HH., o quien ya tiene visibilidad de esta
     * papeleta en el reporte de Sustentos — admin, jefe inmediato y
     * jefe de área (ver ReporteSustentosService::query(), misma
     * frontera). Antes solo dueño/RR. HH.: esos tres la veían listada
     * pero el archivo les devolvía 403 al abrirlo.
     */
    public function verSustento(User $user, Sustento $sustento): bool
    {
        $papeleta = $sustento->papeleta;

        return $papeleta->trabajador_id === $user->id
            || $this->revisarSustento($user, $sustento)
            || $user->hasRole('admin')
            || $this->esJefeVigenteDe($user, $papeleta);
    }

    /**
     * Corrección de datos (hora de retorno, motivo) de una papeleta ya
     * cerrada: solo RRHH y nunca sobre la propia.
     */
    public function corregirComoRrhh(User $user, Papeleta $papeleta): bool
    {
        return $user->hasRole('rrhh')
            && ! $this->esPropia($user, $papeleta)
            && ($papeleta->estado->equals(Cerrada::class)
                || $papeleta->estado->equals(Finalizada::class));
    }
}
