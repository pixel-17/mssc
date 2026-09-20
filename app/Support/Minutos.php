<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Minutos COMPLETOS entre dos instantes: los segundos sobrantes se descartan
 * y nunca sale un valor negativo.
 *
 * Existe porque Carbon 3 devuelve `diffInMinutes()` como float (12.53) y esos
 * valores acababan en `intdiv()`, `%` y funciones tipadas `int` (deprecaciones
 * de PHP 8.1+) y, sobre todo, en sumas: sumar decimales y truncar al final
 * daba otro total que sumar los minutos ya truncados de cada fila. Con un
 * solo criterio, el detalle diario, el resumen mensual y el Excel cuadran.
 *
 * Trabaja con timestamps enteros, así que no depende de cómo se comporte
 * `diffIn*()` en la versión de Carbon instalada.
 */
class Minutos
{
    public static function entre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        return max(0, intdiv($hasta->getTimestamp() - $desde->getTimestamp(), 60));
    }
}
