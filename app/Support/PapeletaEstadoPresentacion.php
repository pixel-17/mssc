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
        // Cada estado tiene su propio tono (excepto Vencida/Cancelada,
        // que son ambos "sin efecto" y comparten familia neutra). Las
        // clases van completas: Tailwind solo genera lo que ve literal.
        return [
            PendienteJefe::class => [
                'Pendiente Jefe',
                'bg-ambar-100 text-ambar-800 ring-ambar-400 dark:bg-ambar-500/20 dark:text-ambar-200 dark:ring-ambar-400/40',
                'bg-ambar-500',
            ],
            ObservadaPorJefe::class => [
                'Observada por Jefe',
                'bg-orange-100 text-orange-800 ring-orange-400 dark:bg-orange-500/20 dark:text-orange-200 dark:ring-orange-400/40',
                'bg-orange-500',
            ],
            PendienteRrhh::class => [
                'Pendiente RRHH',
                'bg-cyan-100 text-cyan-800 ring-cyan-400 dark:bg-cyan-500/20 dark:text-cyan-200 dark:ring-cyan-400/40',
                'bg-cyan-500',
            ],
            ObservadaPorRrhh::class => [
                'Observada por RRHH',
                'bg-pink-100 text-pink-800 ring-pink-400 dark:bg-pink-500/20 dark:text-pink-200 dark:ring-pink-400/40',
                'bg-pink-500',
            ],
            Rechazada::class => [
                'Rechazada',
                'bg-red-100 text-red-800 ring-red-400 dark:bg-red-500/20 dark:text-red-200 dark:ring-red-400/40',
                'bg-red-500',
            ],
            AutorizadaYCorriendo::class => [
                'Autorizada / En curso',
                'bg-lime-100 text-lime-800 ring-lime-400 dark:bg-lime-500/20 dark:text-lime-200 dark:ring-lime-400/40',
                'bg-lime-500',
            ],
            RetornoPendienteSustento::class => [
                'Retorno: falta sustento',
                'bg-morado-100 text-morado-800 ring-morado-400 dark:bg-morado-500/20 dark:text-morado-200 dark:ring-morado-400/40',
                'bg-morado-500',
            ],
            FinalizadoSinRetorno::class => [
                'Sin retorno (abandono)',
                'bg-fuchsia-100 text-fuchsia-800 ring-fuchsia-400 dark:bg-fuchsia-500/20 dark:text-fuchsia-200 dark:ring-fuchsia-400/40',
                'bg-fuchsia-500',
            ],
            Cerrada::class => [
                'Cerrada',
                'bg-verde-100 text-verde-800 ring-verde-400 dark:bg-verde-500/20 dark:text-verde-200 dark:ring-verde-400/40',
                'bg-verde-500',
            ],
            Vencida::class => [
                'Vencida',
                'bg-stone-100 text-stone-800 ring-stone-400 dark:bg-stone-500/20 dark:text-stone-200 dark:ring-stone-400/40',
                'bg-stone-500',
            ],
            Cancelada::class => [
                'Cancelada',
                'bg-gray-100 text-gray-800 ring-gray-400 dark:bg-gray-500/20 dark:text-gray-200 dark:ring-gray-400/40',
                'bg-gray-500',
            ],
            ReclasificadoAParticular::class => [
                'Reclasificada a Particular',
                'bg-indigo-100 text-indigo-800 ring-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200 dark:ring-indigo-400/40',
                'bg-indigo-500',
            ],
        ];
    }
}
