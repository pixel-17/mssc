<?php

namespace App\Support;

/**
 * Color por estado de Sustento (pendiente, presentado, vencido,
 * aprobado, observado). Un solo mapa para el reporte de sustentos y
 * para el detalle de la papeleta, con clases completas y variantes
 * dark: (Tailwind solo genera lo que encuentra literal).
 */
class SustentoEstadoPresentacion
{
    /** @return array{0: string, 1: string} [etiqueta, clases_badge] */
    public static function para(?string $estado): array
    {
        return match ($estado) {
            'pendiente' => ['Pendiente', 'bg-ambar-100 text-ambar-800 ring-ambar-400 dark:bg-ambar-500/20 dark:text-ambar-200 dark:ring-ambar-400/40'],
            'presentado' => ['Presentado', 'bg-azul-100 text-azul-800 ring-azul-400 dark:bg-azul-500/20 dark:text-azul-200 dark:ring-azul-400/40'],
            'aprobado' => ['Aprobado', 'bg-verde-100 text-verde-800 ring-verde-400 dark:bg-verde-500/20 dark:text-verde-200 dark:ring-verde-400/40'],
            'observado' => ['Observado', 'bg-orange-100 text-orange-800 ring-orange-400 dark:bg-orange-500/20 dark:text-orange-200 dark:ring-orange-400/40'],
            'vencido' => ['Vencido', 'bg-red-100 text-red-800 ring-red-400 dark:bg-red-500/20 dark:text-red-200 dark:ring-red-400/40'],
            default => [ucfirst((string) $estado), 'bg-gray-100 text-gray-700 ring-gray-300 dark:bg-white/10 dark:text-gray-300 dark:ring-white/15'],
        };
    }
}
