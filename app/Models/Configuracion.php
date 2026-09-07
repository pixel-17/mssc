<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Parámetros clave-valor: reloj del jefe (minutos), tope de observaciones,
 * bloque de almuerzo, horas hábiles de sustento, días hábiles de
 * subsanación de Emergencia. Nunca hardcodear estos números en el código.
 */
class Configuracion extends Model
{
    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];

    public static function valorDe(string $clave, mixed $default = null): mixed
    {
        return static::where('clave', $clave)->value('valor') ?? $default;
    }
}
