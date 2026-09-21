<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use App\Notifications\PapeletaNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Único punto de verdad de "qué evento del flujo notifica a quién".
 * Cada Action/Command que dispara una transición llama a un método de
 * este servicio en vez de construir la notificación a mano — así, si
 * mañana cambia un destinatario o un mensaje, se cambia en un solo
 * lugar (mismo criterio que RrhhHorarioService/DeterminadorFinDeTurno).
 *
 * Reglas de destinatario tal como están documentadas en el diseño del
 * flujo:
 * - Al crear: notifica al Jefe Inmediato.
 * - Al aprobar el jefe: si RRHH está en horario, se notifica a RRHH
 *   (papeleta pendiente de su decisión); si RRHH está fuera de
 *   horario, se notifica directo al trabajador que puede salir, y a
 *   RRHH que tiene una revisión post-hoc pendiente al iniciar su
 *   jornada.
 * - Al aprobar RRHH: notifica al trabajador que puede salir.
 * - Rechazo u observación del jefe: notifica al trabajador (el mensaje
 *   dice si además debe adjuntar un archivo).
 * - El trabajador responde una observación: notifica al Jefe Inmediato.
 * - Observación de RRHH: notifica al Jefe Inmediato (nunca al
 *   trabajador — la observación de RRHH no le llega directo).
 * - Vencimiento (fin de turno/día sin decisión): notifica al
 *   trabajador.
 * - Abandono no marcado: notifica a Jefe Inmediato y RRHH.
 * - Sustento de Salud vencido sin presentar (reclasificado a
 *   Particular): notifica al trabajador.
 * - Sustento presentado sin revisar a tiempo (requiere visto bueno):
 *   notifica a Jefe Inmediato y RRHH.
 * - Sustento observado (debe volver a presentar): notifica al
 *   trabajador.
 */
class NotificarPapeletaService
{
    public function creada(Papeleta $papeleta): void
    {
        if (! $papeleta->jefeInmediato) {
            return;
        }

        $this->enviarUno(
            $papeleta->jefeInmediato,
            $papeleta,
            'creada_pendiente_jefe',
            'Nueva papeleta por aprobar',
            "{$papeleta->trabajador->nombre_completo} solicitó una papeleta de {$papeleta->motivo->nombre}.",
            $this->urlJefe($papeleta),
        );
    }

    public function pendienteDeRrhh(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->usuariosRrhh(),
            $papeleta,
            'pendiente_rrhh',
            'Papeleta pendiente de tu aprobación',
            "El jefe de {$papeleta->trabajador->nombre_completo} aprobó una papeleta de {$papeleta->motivo->nombre}. Falta tu decisión.",
            fn () => $this->urlRrhh($papeleta),
        );
    }

    public function puedeSalir(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'autorizada',
            'Papeleta autorizada',
            'Tu papeleta fue autorizada. Ya puedes salir.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function revisionPosthocPendiente(Papeleta $papeleta): void
    {
        $this->enviar(
            $this->usuariosRrhh(),
            $papeleta,
            'posthoc_pendiente',
            'Revisión post-hoc pendiente',
            "El jefe de {$papeleta->trabajador->nombre_completo} autorizó una salida fuera de tu horario. Requiere tu revisión.",
            fn () => $this->urlRrhh($papeleta),
        );
    }

    public function rechazada(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'rechazada',
            'Papeleta rechazada',
            $papeleta->motivo_rechazo ?: 'Tu papeleta fue rechazada.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function observadaPorJefe(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'observada_jefe',
            'Papeleta observada',
            $papeleta->observacion_requiere_adjunto
                ? 'Tu jefe observó tu papeleta. Responde por escrito y adjunta el archivo que te pide para continuar.'
                : 'Tu jefe observó tu papeleta. Revisa el comentario y respóndelo por escrito para continuar.',
            $this->urlTrabajador($papeleta),
        );
    }

    /** El trabajador respondió la observación: la papeleta vuelve a la decisión del jefe. */
    public function observacionRespondida(Papeleta $papeleta): void
    {
        if (! $papeleta->jefeInmediato) {
            return;
        }

        $this->enviarUno(
            $papeleta->jefeInmediato,
            $papeleta,
            'observacion_respondida',
            'Observación respondida',
            "{$papeleta->trabajador->nombre_completo} respondió tu observación. La papeleta volvió a tu bandeja.",
            $this->urlJefe($papeleta),
        );
    }

    /**
     * A diferencia de observadaPorJefe(), esta NUNCA llega al
     * trabajador — la observación de RRHH la resuelve el Jefe
     * Inmediato (ver ReconocerObservacionRrhhAction).
     */
    public function observadaPorRrhh(Papeleta $papeleta): void
    {
        if (! $papeleta->jefeInmediato) {
            return;
        }

        $this->enviarUno(
            $papeleta->jefeInmediato,
            $papeleta,
            'observada_rrhh',
            'RRHH observó una papeleta de tu equipo',
            "RRHH observó la papeleta de {$papeleta->trabajador->nombre_completo}. Coordina la subsanación.",
            $this->urlJefe($papeleta),
        );
    }

    public function vencida(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'vencida',
            'Papeleta vencida',
            'Tu turno/día terminó sin que se decidiera tu papeleta. Quedó marcada como vencida.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function abandonoNoMarcado(Papeleta $papeleta): void
    {
        $destinatarios = $this->usuariosRrhh();

        if ($papeleta->jefeInmediato) {
            $destinatarios->push($papeleta->jefeInmediato);
        }

        $this->enviar(
            $destinatarios,
            $papeleta,
            'abandono_no_marcado',
            'Turno finalizado sin marcar retorno',
            "{$papeleta->trabajador->nombre_completo} no marcó su retorno antes de que terminara su turno/día.",
            fn (User $u) => $this->urlSegunRol($papeleta, $u),
        );
    }

    public function reclasificadaAParticular(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'reclasificada_particular',
            'Papeleta reclasificada a Particular',
            'El plazo para presentar sustento de Salud venció sin nada presentado. Tu papeleta se reclasificó a Particular.',
            $this->urlTrabajador($papeleta),
        );
    }

    public function sustentoSinRevisar(Papeleta $papeleta): void
    {
        $destinatarios = $this->usuariosRrhh();

        if ($papeleta->jefeInmediato) {
            $destinatarios->push($papeleta->jefeInmediato);
        }

        $this->enviar(
            $destinatarios,
            $papeleta,
            'sustento_sin_revisar',
            'Sustento pendiente de revisión',
            "Venció el plazo del sustento de {$papeleta->trabajador->nombre_completo} y todavía no tiene decisión.",
            fn (User $u) => $this->urlSegunRol($papeleta, $u),
        );
    }

    public function sustentoObservado(Papeleta $papeleta): void
    {
        $this->enviarUno(
            $papeleta->trabajador,
            $papeleta,
            'sustento_observado',
            'Sustento observado',
            'El sustento que presentaste fue observado. Vuelve a subir un archivo antes de que venza el plazo.',
            $this->urlTrabajador($papeleta),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function usuariosRrhh(): Collection
    {
        return User::role('rrhh')->get();
    }

    private function enviarUno(User $destinatario, Papeleta $papeleta, string $tipo, string $titulo, string $mensaje, ?string $url): void
    {
        Notification::send($destinatario, new PapeletaNotification($papeleta, $tipo, $titulo, $mensaje, $url));
    }

    /**
     * @param  Collection<int, User>  $destinatarios
     * @param  callable(User): ?string  $url
     */
    private function enviar(Collection $destinatarios, Papeleta $papeleta, string $tipo, string $titulo, string $mensaje, callable $url): void
    {
        foreach ($destinatarios->unique('id') as $destinatario) {
            $this->enviarUno($destinatario, $papeleta, $tipo, $titulo, $mensaje, $url($destinatario));
        }
    }

    private function urlSegunRol(Papeleta $papeleta, User $usuario): ?string
    {
        if ($usuario->hasRole('rrhh')) {
            return $this->urlRrhh($papeleta);
        }

        return $this->urlJefe($papeleta);
    }

    private function urlTrabajador(Papeleta $papeleta): ?string
    {
        return route('trabajador.papeletas.show', $papeleta);
    }

    private function urlJefe(Papeleta $papeleta): ?string
    {
        return route('jefe.papeletas.show', $papeleta);
    }

    private function urlRrhh(Papeleta $papeleta): ?string
    {
        return route('rrhh.papeletas.show', $papeleta);
    }
}
