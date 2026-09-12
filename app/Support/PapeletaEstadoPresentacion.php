<?php

namespace App\Support;

use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\FinalizadoSinRetorno;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\ReclasificadoAParticular;
use App\States\Papeleta\Rechazada;
use App\States\Papeleta\RetornoPendienteSustento;
use App\States\Papeleta\Vencida;
use Spatie\ModelStates\State;

/**
 * Mapa único de presentación visual por estado de Papeleta (etiqueta,
 * clases de badge suave con soporte dark:, y color sólido para
 * puntos/ribbons). Antes vivía duplicado dentro de
 * components/estado-papeleta.blade.php; ahora también lo usa
 * components/papeleta-ticket.blade.php (bandeja móvil del
 * trabajador), así que un mismo estado siempre se ve igual en toda
 * la app.
 */
class PapeletaEstadoPresentacion
{
    /**
     * @return array{0: string, 1: string, 2: string} [etiqueta, clases_badge, clase_solida]
     */
    public static function para(State|string $estado): array
    {
        $clave = $estado instanceof State ? get_class($estado) : (string) $estado;

        return self::mapa()[$clave] ?? [
            class_basename($clave),
            'bg-gray-100 text-gray-800 ring-gray-300 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15',
            'bg-gray-400',
        ];
    }

    /** @return array<class-string, array{0:string,1:string,2:string}> */
    protected static function mapa(): array
    {
        return [
            PendienteJefe::class => [
                'Pendiente Jefe',
                'bg-amber-50 text-amber-800 ring-amber-300 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-400/30',
                'bg-amber-500',
            ],
            ObservadaPorJefe::class => [
                'Observada por Jefe',
                'bg-orange-50 text-orange-800 ring-orange-300 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-400/30',
                'bg-orange-500',
            ],
            PendienteRrhh::class => [
                'Pendiente RRHH',
                'bg-amber-50 text-amber-800 ring-amber-300 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-400/30',
                'bg-amber-500',
            ],
            ObservadaPorRrhh::class => [
                'Observada por RRHH',
                'bg-orange-50 text-orange-800 ring-orange-300 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-400/30',
                'bg-orange-500',
            ],
            Rechazada::class => [
                'Rechazada',
                'bg-red-50 text-red-800 ring-red-300 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-400/30',
                'bg-red-500',
            ],
            AutorizadaYCorriendo::class => [
                'Autorizada / En curso',
                'bg-ocean-50 text-ocean-800 ring-ocean-300 dark:bg-ocean-500/15 dark:text-ocean-200 dark:ring-ocean-400/30',
                'bg-ocean-500',
            ],
            RetornoPendienteSustento::class => [
                'Retorno: falta sustento',
                'bg-purple-50 text-purple-800 ring-purple-300 dark:bg-purple-500/15 dark:text-purple-300 dark:ring-purple-400/30',
                'bg-purple-500',
            ],
            FinalizadoSinRetorno::class => [
                'Sin retorno (abandono)',
                'bg-red-50 text-red-800 ring-red-300 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-400/30',
                'bg-red-500',
            ],
            Cerrada::class => [
                'Cerrada',
                'bg-emerald-50 text-emerald-800 ring-emerald-300 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-400/30',
                'bg-emerald-500',
            ],
            Vencida::class => [
                'Vencida',
                'bg-gray-100 text-gray-700 ring-gray-300 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15',
                'bg-gray-400',
            ],
            Cancelada::class => [
                'Cancelada',
                'bg-gray-100 text-gray-700 ring-gray-300 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15',
                'bg-gray-400',
            ],
            ReclasificadoAParticular::class => [
                'Reclasificada a Particular',
                'bg-purple-50 text-purple-800 ring-purple-300 dark:bg-purple-500/15 dark:text-purple-300 dark:ring-purple-400/30',
                'bg-purple-500',
            ],
        ];
    }
}
