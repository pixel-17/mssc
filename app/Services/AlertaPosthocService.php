<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Papeleta;
use App\Models\User;
use App\Notifications\AlertaOrganizacionalNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa cuando la cola de revisión post-hoc de RRHH se acumula.
 *
 * Toda salida autorizada con RRHH fuera de horario (papeleta de un 728 de
 * noche, fin de semana o feriado, o de cualquier jefe cuando RRHH no
 * atiende) queda con `revision_posthoc_estado = 'pendiente'` y RRHH debe
 * revisarla. Cada una ya avisa individualmente al crearse
 * (NotificarPapeletaService::revisionPosthocPendiente); lo que faltaba era
 * un aviso cuando la cola crece o envejece sin que nadie la atienda.
 *
 * Se alerta si se cumple CUALQUIERA de estas condiciones (ambas editables
 * en Configuraciones):
 *
 * - POSTHOC_ALERTA_CANTIDAD: hay al menos N revisiones pendientes.
 * - POSTHOC_ALERTA_HORAS: la pendiente más antigua lleva al menos H horas
 *   desde que se autorizó la salida (el flujo pide revisarla al día
 *   siguiente, por eso 24 h por defecto).
 *
 * A lo mucho un aviso por día calendario, para que el comando horario no
 * repita la alerta cada hora mientras la cola siga alta.
 *
 * Destinatarios: personal RRHH activo (con enlace a su bandeja) y admin
 * activo que no sea también RRHH (sin enlace: la bandeja es solo de RRHH).
 */
class AlertaPosthocService
{
    public const CANTIDAD_DEFECTO = 10;

    public const HORAS_DEFECTO = 24;

    /**
     * @return bool true si se envió una alerta en esta corrida
     */
    public function evaluar(): bool
    {
        $total = $this->pendientes()->count();

        if ($total === 0) {
            return false;
        }

        $umbralCantidad = max(1, (int) Configuracion::valorDe('POSTHOC_ALERTA_CANTIDAD', self::CANTIDAD_DEFECTO));
        $umbralHoras = max(1, (int) Configuracion::valorDe('POSTHOC_ALERTA_HORAS', self::HORAS_DEFECTO));

        $horasDeLaMasAntigua = $this->horasDeLaMasAntigua();

        $porCantidad = $total >= $umbralCantidad;
        $porAntiguedad = $horasDeLaMasAntigua >= $umbralHoras;

        if (! $porCantidad && ! $porAntiguedad) {
            return false;
        }

        // Cache::add solo escribe si la clave no existía: la primera
        // corrida del día gana y las demás no repiten el aviso.
        if (! Cache::add('alerta-posthoc:'.now()->toDateString(), true, now()->endOfDay())) {
            return false;
        }

        $this->avisar($total, $horasDeLaMasAntigua);

        return true;
    }

    /**
     * Revisiones que están en manos de RRHH: las que nunca se revisaron
     * ('pendiente') y las que el jefe ya respondió ('respondida'). Las
     * 'observada' esperan al jefe, no a RRHH, y no cuentan aquí.
     *
     * @return Builder<Papeleta>
     */
    private function pendientes(): Builder
    {
        return Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
            ->whereIn('revision_posthoc_estado', ['pendiente', 'respondida']);
    }

    /**
     * Horas completas que lleva la revisión pendiente más antigua, contadas
     * desde que salió el trabajador (hora_salida_real) o, si esa columna
     * no está, desde que se creó la papeleta.
     */
    private function horasDeLaMasAntigua(): int
    {
        $masAntigua = $this->pendientes()
            ->selectRaw('MIN(COALESCE(hora_salida_real, created_at)) as desde')
            ->value('desde');

        if ($masAntigua === null) {
            return 0;
        }

        return (int) Carbon::parse($masAntigua)->diffInHours(now(), true);
    }

    private function avisar(int $total, int $horasDeLaMasAntigua): void
    {
        $titulo = 'Revisiones post-hoc acumuladas';
        $mensaje = $total === 1
            ? "Hay 1 revisión post-hoc pendiente; lleva {$horasDeLaMasAntigua} h sin revisarse."
            : "Hay {$total} revisiones post-hoc pendientes; la más antigua lleva {$horasDeLaMasAntigua} h sin revisarse.";

        $rrhh = User::role('rrhh')->where('activo', true)->get();

        $admins = User::role('admin')
            ->where('activo', true)
            ->whereNotIn('id', $rrhh->modelKeys())
            ->get();

        if ($rrhh->isNotEmpty()) {
            Notification::send(
                $rrhh,
                new AlertaOrganizacionalNotification('posthoc_acumulado', $titulo, $mensaje, route('rrhh.papeletas.index'))
            );
        }

        if ($admins->isNotEmpty()) {
            Notification::send(
                $admins,
                new AlertaOrganizacionalNotification('posthoc_acumulado', $titulo, $mensaje)
            );
        }
    }
}
