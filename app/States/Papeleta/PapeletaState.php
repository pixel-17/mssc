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
            ->allowTransition(ObservadaPorJefe::class, Vencida::class)

            ->allowTransition(PendienteRrhh::class, ObservadaPorRrhh::class)
            ->allowTransition(PendienteRrhh::class, Rechazada::class)
            ->allowTransition(PendienteRrhh::class, AutorizadaYCorriendo::class)
            ->allowTransition(PendienteRrhh::class, Vencida::class)

            ->allowTransition(ObservadaPorRrhh::class, PendienteJefe::class) // SIEMPRE vuelve al jefe, nunca al trabajador
            ->allowTransition(ObservadaPorRrhh::class, Vencida::class)

            ->allowTransition(AutorizadaYCorriendo::class, Cerrada::class)
            ->allowTransition(AutorizadaYCorriendo::class, RetornoPendienteSustento::class)
            ->allowTransition(AutorizadaYCorriendo::class, FinalizadoSinRetorno::class)
            ->allowTransition(AutorizadaYCorriendo::class, ReclasificadoAParticular::class) // Emergencia observada sin subsanar

            // Paso 6: la revisión post-hoc de Emergencia NO bloquea el
            // ciclo operativo — el trabajador puede retornar y cerrar
            // normalmente ANTES de que jefe/RRHH terminen de revisar.
            // Si luego de cerrada la observación no se subsana a tiempo,
            // igual debe poder reclasificarse a Particular.
            ->allowTransition(Cerrada::class, ReclasificadoAParticular::class) // Emergencia observada, ya cerrada, subsanación vencida

            ->allowTransition(RetornoPendienteSustento::class, Cerrada::class)
            ->allowTransition(RetornoPendienteSustento::class, ReclasificadoAParticular::class)
            ->allowTransition(RetornoPendienteSustento::class, FinalizadoSinRetorno::class); // abandono gana sobre sustento vencido
    }
}
