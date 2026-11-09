<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Parámetros clave-valor: reloj del jefe (minutos), tope de observaciones,
 * bloque de almuerzo, horas hábiles de sustento. Nunca hardcodear
 * estos números en el código.
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];

    /**
     * Cacheada indefinidamente por clave: valorDe() se llama ~22 veces
     * en el código (varias dentro de comandos que recorren papeletas o
     * trabajadores uno por uno), y estos valores casi nunca cambian.
     * Se invalida sola en cuanto se guarda o borra la fila (ver
     * booted()), así que nunca sirve un valor viejo tras editar en
     * Configuraciones.
     */
    public static function valorDe(string $clave, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            self::claveCache($clave),
            fn () => static::where('clave', $clave)->value('valor')
        ) ?? $default;
    }

    /**
     * Igual que valorDe(), pero ya resuelto a int: evita repetir en cada
     * punto de uso un (int) sobre un valor mixed (PHPStan nivel 9 lo
     * rechaza como cast inseguro).
     */
    public static function valorEnteroDe(string $clave, int $default = 0): int
    {
        $valor = self::valorDe($clave, $default);

        return is_numeric($valor) ? (int) $valor : $default;
    }

    /**
     * Igual que valorDe(), pero ya resuelto a string: evita repetir en
     * cada punto de uso un (string) sobre un valor mixed.
     */
    public static function valorTextoDe(string $clave, string $default = ''): string
    {
        $valor = self::valorDe($clave, $default);

        return is_scalar($valor) ? (string) $valor : $default;
    }

    protected static function booted(): void
    {
        static::saved(fn (self $configuracion) => Cache::forget(self::claveCache($configuracion->clave)));
        static::deleted(fn (self $configuracion) => Cache::forget(self::claveCache($configuracion->clave)));
    }

    protected static function claveCache(string $clave): string
    {
        return "configuracion:{$clave}";
    }
}
