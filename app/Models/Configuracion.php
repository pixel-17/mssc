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

    /**
     * Versión legible del valor para el listado admin (ver
     * configuracion-index.blade.php): "1,2,3,4,5" como "Lunes, Martes...",
     * las horas con su sufijo y los minutos/horas/días hábiles con su
     * unidad. No se usa para validar ni guardar, solo para mostrar — el
     * valor crudo sigue siendo el que se edita en ConfiguracionForm.
     */
    public function valorFormateado(): string
    {
        if (preg_match('/(_HORA_(INICIO|FIN)|^BLOQUE_ALMUERZO_(INICIO|FIN))$/', $this->clave)) {
            return "{$this->valor} h";
        }

        if ($this->clave === 'HORARIO_ORDINARIO_DIAS_LABORABLES') {
            $nombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

            return collect(explode(',', $this->valor))
                ->filter(fn ($dia) => $dia !== '')
                ->map(fn ($dia) => $nombres[((int) $dia) - 1] ?? $dia)
                ->implode(', ');
        }

        if (str_ends_with($this->clave, '_MINUTOS')) {
            return "{$this->valor} min";
        }

        if (str_ends_with($this->clave, '_HORAS_HABILES')) {
            return "{$this->valor} horas hábiles";
        }

        if (str_ends_with($this->clave, '_DIAS_HABILES')) {
            return "{$this->valor} días hábiles";
        }

        return $this->valor;
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