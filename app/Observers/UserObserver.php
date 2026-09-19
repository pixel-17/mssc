<?php

namespace App\Observers;

use App\Models\User;

/**
 * Dos responsabilidades separadas, cada una en su propio hook:
 *
 * 1) saving(): mantiene sincronizados jefe_inmediato_id / jefe_area_id
 *    (valores explícitos que las papeletas fotografían) con la
 *    posición real del usuario en el árbol de unidades_organicas.
 *    Estos dos campos NO se editan a mano desde ningún formulario: se
 *    recalculan solos cada vez que cambia unidad_organica_id. Si en
 *    algún momento se necesita reasignar jefe sin mover al trabajador
 *    de unidad, eso se hace cambiando jefe_id en la UnidadOrganica
 *    (ver UnidadOrganicaObserver), no aquí.
 *
 * 2) saved(): si cambió sede_id, resincroniza los `turnos` futuros ya
 *    generados por GeneradorTurnoMensualService (ver ese método para
 *    el por qué).
 */
class UserObserver
{
    public function saving(User $user): void
    {
        if (! $user->isDirty('unidad_organica_id')) {
            return;
        }

        $this->resincronizar($user);
    }

    public function resincronizar(User $user): void
    {
        $unidad = $user->unidad_organica_id
            ? \App\Models\UnidadOrganica::find($user->unidad_organica_id)
            : null;

        $user->jefe_inmediato_id = $unidad?->jefeInmediato()?->id;
        $user->jefe_area_id = $unidad?->jefeArea()?->id;
    }

    /**
     * Los `turnos` que genera GeneradorTurnoMensualService copian
     * sede_id del trabajador en el momento en que se generan (mes en
     * curso o el siguiente, ver el servicio) — no lo leen en vivo
     * cada vez. Si el admin cambia la sede después, esos días ya
     * generados que todavía no pasaron se quedarían con la sede
     * vieja hasta el próximo mes. Se resincronizan aquí para que no
     * dependa de que alguien vuelva a tocar su configuración de turno.
     *
     * Solo desde hoy en adelante: los días pasados quedan tal como
     * estaban (es historial, no hay que reescribirlo).
     */
    public function saved(User $user): void
    {
        if (! $user->wasChanged('sede_id')) {
            return;
        }

        \App\Models\Turno::where('user_id', $user->id)
            ->where('fecha', '>=', now()->toDateString())
            ->update(['sede_id' => $user->sede_id]);

        \App\Events\HorarioActualizado::notificar($user->id);
    }
}
