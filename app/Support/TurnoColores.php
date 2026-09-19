<?php

namespace App\Support;

/**
 * Paleta única de los turnos en TODOS los calendarios (individual,
 * equipo, programación mensual y por equipo). Cada tipo de turno tiene
 * su propio tono de acento —amarillo, verde, morado, azul y gris— para
 * que un vistazo baste sin leer la sigla.
 *
 * Las clases están escritas completas (no armadas con variables)
 * porque Tailwind solo genera lo que encuentra literal en los archivos
 * que escanea; app/Support/ está en su `content`.
 */
class TurnoColores
{
    /** @return array<string, string> sigla => clases (claro + oscuro) */
    public static function porSigla(): array
    {
        return [
            'M' => 'bg-ambar-200 text-ambar-900 border-ambar-400 dark:bg-ambar-500/25 dark:text-ambar-200 dark:border-ambar-400/50',
            'T' => 'bg-verde-200 text-verde-900 border-verde-400 dark:bg-verde-500/25 dark:text-verde-200 dark:border-verde-400/50',
            'N' => 'bg-morado-200 text-morado-900 border-morado-400 dark:bg-morado-500/25 dark:text-morado-200 dark:border-morado-400/50',
            'D' => 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-500/10 dark:text-gray-400 dark:border-gray-500/20',
            'DIA' => 'bg-azul-200 text-azul-900 border-azul-400 dark:bg-azul-500/25 dark:text-azul-200 dark:border-azul-400/50',
        ];
    }

    public static function para(string $sigla): string
    {
        $mapa = self::porSigla();

        return $mapa[$sigla] ?? $mapa['DIA'];
    }

    /**
     * Misma paleta indexada por el código que usan los pinceles de
     * programación (Alpine): MANANA, TARDE, NOCHE, DESCANSO.
     *
     * @return array<string, string>
     */
    public static function porCodigo(): array
    {
        $mapa = self::porSigla();

        return [
            'MANANA' => $mapa['M'],
            'TARDE' => $mapa['T'],
            'NOCHE' => $mapa['N'],
            'DESCANSO' => $mapa['D'],
        ];
    }
}
