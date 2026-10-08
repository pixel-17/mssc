<?php

namespace App\States\Papeleta;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Estados de la papeleta (documento de flujo, sección "Estados
 * terminales" + diagrama de Pasos 1-6). Los estados terminales
 * sobreescriben esTerminal() a true; el resto se queda en false.
 */
abstract class PapeletaState extends State
{
    abstract public function esTerminal(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendienteJefe::class)
            ->allowTransition(PendienteJefe::class, ObservadaPorJefe::class)
            ->allowTransition(PendienteJefe::class, Rechazada::class)
            ->allowTransition(PendienteJefe::class, PendienteRrhh::class)
            ->allowTransition(PendienteJefe::class, AutorizadaYCorriendo::class) // RRHH fuera de horario
            ->allowTransition(PendienteJefe::class, Vencida::class)
            ->allowTransition(PendienteJefe::class, Cancelada::class)

            ->allowTransition(ObservadaPorJefe::class, PendienteJefe::class) // visto bueno explícito, reinicia reloj
            ->allowTransition(ObservadaPorJefe::class, Rechazada::class) // tope de 3 observaciones
            // AprobarJefeAction acepta ObservadaPorJefe como origen (el jefe da su
            // visto bueno directo sin pasar antes por PendienteJefe): ambas ramas
            // de destino deben existir aquí o transicionarA() las rechazaría.
            ->allowTransition(ObservadaPorJefe::class, PendienteRrhh::class)
            ->allowTransition(ObservadaPorJefe::class, AutorizadaYCorriendo::class)
            ->allowTransition(ObservadaPorJefe::class, Vencida::class)
            ->allowTransition(ObservadaPorJefe::class, Cancelada::class) // el trabajador desiste tras la observación

            ->allowTransition(PendienteRrhh::class, ObservadaPorRrhh::class)
            ->allowTransition(PendienteRrhh::class, Rechazada::class)
            ->allowTransition(PendienteRrhh::class, AutorizadaYCorriendo::class)
            ->allowTransition(PendienteRrhh::class, Vencida::class)
            ->allowTransition(PendienteRrhh::class, Cancelada::class) // solo con RRHH en horario (ver CancelarPapeletaAction)

            ->allowTransition(ObservadaPorRrhh::class, PendienteJefe::class) // SIEMPRE vuelve al jefe, nunca al trabajador
            ->allowTransition(ObservadaPorRrhh::class, Vencida::class)
            ->allowTransition(ObservadaPorRrhh::class, Cancelada::class) // solo con RRHH en horario (ver CancelarPapeletaAction)

            ->allowTransition(AutorizadaYCorriendo::class, Cerrada::class)
            ->allowTransition(AutorizadaYCorriendo::class, Finalizada::class)
            ->allowTransition(AutorizadaYCorriendo::class, EnJustificacion::class)

            // Post-hoc no aprobado por RRHH: una papeleta ya Cerrada (sin descuento)
            // pasa a Particular -> Finalizada (con descuento).
            ->allowTransition(Cerrada::class, Finalizada::class)

            // Aprobada -> Cerrada (sin descuento). Rechazada, vencida o
            // abandono marcado por una persona -> Finalizada (con descuento).
            ->allowTransition(EnJustificacion::class, Cerrada::class)
            ->allowTransition(EnJustificacion::class, Finalizada::class);
    }
}
