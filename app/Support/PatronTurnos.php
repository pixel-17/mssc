<?php

namespace App\Support;

use DateTimeImmutable;

/**
 * Patrón repetido de turnos para régimen 728 ("M M T T N D").
 *
 * Lógica pura (sin base de datos) para poder probarla de forma aislada.
 * La misma lógica existe en JavaScript en programacion-mensual.blade.php
 * para el caso de un solo mes (pintado inmediato en el navegador); esta
 * versión es la que usa el servidor al generar varios meses.
 */
final class PatronTurnos
{
    /** Sigla → código de turno. */
    public const CLAVES = [
        'M' => 'MANANA',
        'T' => 'TARDE',
        'N' => 'NOCHE',
        'D' => 'DESCANSO',
    ];

    /** Atajos que se ofrecen en pantalla: etiqueta => patrón. */
    public const PREDEFINIDOS = [
        '6x1 Mañana' => 'M M M M M M D',
        '6x1 Tarde' => 'T T T T T T D',
        '6x1 Noche' => 'N N N N N N D',
        'Rotativo M M T T N D' => 'M M T T N D',
    ];

    /** Tope de meses por generación: evita programar un año entero por error. */
    public const MAX_MESES = 6;

    /**
     * "M M t,t n d" → ['MANANA', 'MANANA', 'TARDE', 'TARDE', 'NOCHE', 'DESCANSO'].
     * Null si está vacío o trae algo distinto de M, T, N o D.
     *
     * @return array<int, string>|null
     */
    public static function parsear(string $patron): ?array
    {
        $tokens = preg_split('/[\s,]+/', mb_strtoupper(trim($patron)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return null;
        }

        $pasos = [];
        foreach ($tokens as $token) {
            if (! isset(self::CLAVES[$token])) {
                return null;
            }
            $pasos[] = self::CLAVES[$token];
        }

        return $pasos;
    }

    /**
     * Posición del patrón con la que arranca el día siguiente al último
     * de $previos, para que la rotación siga donde terminó el mes anterior.
     *
     * $previos son los códigos de los días inmediatamente anteriores, del
     * más antiguo al más reciente; null = día sin programar. Se busca la
     * posición cuya racha de coincidencias hacia atrás sea la más larga;
     * si el último día previo no está programado o no coincide con nada,
     * devuelve 0 (arranca el patrón desde el principio).
     *
     * Con patrones muy repetitivos (M M M M M M D) un solo día previo puede
     * coincidir con varias posiciones: se toma la primera. Cuantos más días
     * previos se envíen (idealmente tantos como pasos tiene el patrón),
     * menos ambigüedad hay.
     *
     * @param  array<int, string>  $pasos
     * @param  array<int, string|null>  $previos
     */
    public static function offsetContinuacion(array $pasos, array $previos): int
    {
        $n = count($pasos);
        $previos = array_values($previos);
        $largo = count($previos);

        if ($n === 0 || $largo === 0) {
            return 0;
        }

        $mejorPosicion = null;
        $mejorRacha = 0;

        for ($posicion = 0; $posicion < $n; $posicion++) {
            // Suponemos que el último día previo ocupó esta posición del patrón.
            $racha = 0;
            for ($k = 0; $k < $largo; $k++) {
                $codigo = $previos[$largo - 1 - $k];
                if ($codigo === null || $pasos[(($posicion - $k) % $n + $n) % $n] !== $codigo) {
                    break;
                }
                $racha++;
            }

            if ($racha > $mejorRacha) {
                $mejorRacha = $racha;
                $mejorPosicion = $posicion;
            }
        }

        return $mejorPosicion === null ? 0 : ($mejorPosicion + 1) % $n;
    }

    /**
     * Repite el patrón día por día entre $desde y $hasta (ambos incluidos,
     * 'Y-m-d'), arrancando en la posición $offset.
     *
     * @param  array<int, string>  $pasos
     * @return array<string, string> 'Y-m-d' => código
     */
    public static function generar(array $pasos, string $desde, string $hasta, int $offset = 0): array
    {
        $n = count($pasos);
        if ($n === 0) {
            return [];
        }

        $dias = [];
        $fecha = new DateTimeImmutable($desde);
        $fin = new DateTimeImmutable($hasta);

        for ($i = 0; $fecha <= $fin; $i++) {
            $dias[$fecha->format('Y-m-d')] = $pasos[($offset + $i) % $n];
            $fecha = $fecha->modify('+1 day');
        }

        return $dias;
    }
}
