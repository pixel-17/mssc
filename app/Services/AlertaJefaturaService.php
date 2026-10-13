<?php

namespace App\Services;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Notifications\AlertaOrganizacionalNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Único punto de verdad de "a quién se avisa cuando a una unidad con gente
 * le falta su jefe" (sin jefe asignado, o con el jefe dado de baja).
 *
 * Se dispara de forma periódica sobre todo el organigrama (ver comando
 * jefaturas:avisar-faltantes), para detectar huecos que aparecen sin que
 * nadie esté tocando la unidad justo en ese momento (p. ej. una baja
 * dejando la jefatura vacante a propósito).
 *
 * Una jefatura vacante solo afecta a quien depende de ella (ver
 * CrearPapeletaAction): no bloquea al resto del organigrama.
 *
 * Destinatarios: todos los admin + el Jefe de Área de la unidad (el
 * jefe de la unidad padre — mismo criterio que UnidadOrganica::jefeArea()).
 * Si la unidad es la raíz del árbol y no tiene Jefe de Área, solo llega
 * a admin.
 */
class AlertaJefaturaService
{
    /**
     * @return int cantidad de avisos disparados
     */
    public function avisarFaltantesEnTodoElOrganigrama(): int
    {
        $avisos = 0;

        $unidades = UnidadOrganica::where('activo', true)
            ->with(['jefe', 'padre.jefe'])
            ->where(fn ($q) => $q
                ->whereHas('miembros', fn ($m) => $m->where('activo', true))
                ->orWhereHas('hijos', fn ($h) => $h->where('activo', true)))
            ->get();

        foreach ($unidades as $unidad) {
            if (! $unidad->jefe?->activo) {
                $this->avisar($unidad);
                $avisos++;
            }
        }

        return $avisos;
    }

    private function avisar(UnidadOrganica $unidad): void
    {
        $destinatarios = User::role('admin')->get();

        $jefeArea = $unidad->jefeArea();

        if ($jefeArea?->activo) {
            $destinatarios->push($jefeArea);
        }

        Notification::send(
            $destinatarios->unique('id'),
            new AlertaOrganizacionalNotification(
                'jefatura_faltante',
                'Unidad sin jefe',
                "La unidad \"{$unidad->nombre}\" no tiene un jefe activo asignado.",
            )
        );
    }
}
