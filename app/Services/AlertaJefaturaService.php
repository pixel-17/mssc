<?php

namespace App\Services;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Notifications\AlertaOrganizacionalNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Único punto de verdad de "a quién se avisa cuando a una unidad le
 * falta un jefe inmediato ACTIVO en alguno de sus turnos (MANANA/
 * TARDE/NOCHE)". Se dispara en dos momentos:
 *
 * - Al crear un trabajador cuyo turno vigente queda sin jefe
 *   (ver CrearUsuarioAction).
 * - De forma periódica sobre todo el organigrama (ver comando
 *   jefaturas:avisar-faltantes), para detectar huecos que aparecen
 *   sin que nadie esté dando de alta a nadie justo en ese momento
 *   (p. ej. se desactiva al único jefe de un turno).
 *
 * Destinatarios: todos los admin + el Jefe de Área de la unidad (el
 * jefe de la unidad padre — mismo criterio que
 * UnidadOrganica::jefeArea()). Si la unidad es la raíz del árbol y no
 * tiene Jefe de Área, solo llega a admin.
 */
class AlertaJefaturaService
{
    /**
     * Turnos que aplican para régimen 728 — 276 no usa jefes_turno
     * (un solo jefe fijo, jefe_id de la unidad, ver
     * UnidadOrganica::resolverJefeInmediato()).
     */
    private const TURNOS = ['MANANA', 'TARDE', 'NOCHE'];

    /**
     * Avisa si, AHORA MISMO, el turno dado de esta unidad no resuelve
     * a un jefe inmediato activo. No hace nada si sí lo tiene — se
     * llama sin chequear antes, para no duplicar la regla en cada
     * punto de llamada.
     */
    public function avisarSiFaltaJefeDeTurno(UnidadOrganica $unidad, ?string $turno): void
    {
        if ($turno === null) {
            return;
        }

        if ($this->tieneJefeActivo($unidad, $turno)) {
            return;
        }

        $this->avisar($unidad, $turno);
    }

    /**
     * Revisa TODAS las unidades activas que usan cobertura por turno
     * (régimen 728, al menos una fila en jefes_turno) y avisa cada
     * hueco encontrado. Pensado para correr periódicamente (ver
     * comando jefaturas:avisar-faltantes).
     *
     * @return int cantidad de avisos disparados
     */
    public function avisarFaltantesEnTodoElOrganigrama(): int
    {
        $avisos = 0;

        $unidades = UnidadOrganica::where('activo', true)
            ->whereHas('jefesTurno')
            ->with(['jefesTurno.jefe', 'jefe', 'padre.jefe'])
            ->get();

        foreach ($unidades as $unidad) {
            foreach (self::TURNOS as $turno) {
                if (! $this->tieneJefeActivo($unidad, $turno)) {
                    $this->avisar($unidad, $turno);
                    $avisos++;
                }
            }
        }

        return $avisos;
    }

    private function tieneJefeActivo(UnidadOrganica $unidad, string $turno): bool
    {
        $jefeId = $unidad->resolverJefeInmediato($turno);
        $jefe = $jefeId ? User::find($jefeId) : null;

        return $jefe !== null && $jefe->activo;
    }

    private function avisar(UnidadOrganica $unidad, string $turno): void
    {
        $destinatarios = User::role('admin')->get();

        $jefeArea = $unidad->jefeArea();

        if ($jefeArea) {
            $destinatarios->push($jefeArea);
        }

        Notification::send(
            $destinatarios->unique('id'),
            new AlertaOrganizacionalNotification(
                'jefatura_faltante',
                'Falta jefe de turno',
                "El turno {$turno} de \"{$unidad->nombre}\" no tiene un jefe inmediato activo asignado.",
            )
        );
    }
}
