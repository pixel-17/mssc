<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Horario de UN usuario en UNA fecha, cargado por el admin.
 *
 * Desde el rediseño de régimen 276 (ordinario): esta tabla ya NO se usa
 * para 276 — ese horario es único y global (ver HorarioOrdinarioService,
 * editable en Configuraciones), para no obligar al admin a cargar una
 * fila por cada uno de los ~500 trabajadores 276 todos los días.
 *
 * Para 728 (rotativo) sigue existiendo tal como antes: informativa y
 * opcional. Por defecto el sistema no bloquea nada contra ella para
 * ese régimen (ni al crear papeleta ni al escalar); la única
 * excepción es el interruptor global MODO_ESTRICTO_728 (tabla
 * configuraciones), que si está activo SÍ bloquea la creación de
 * papeleta cuando no hay turno vigente (ver CrearPapeletaAction).
 */
class Turno extends Model
{
    protected $fillable = [
        'user_id',
        'sede_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'es_descanso',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'es_descanso' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * ¿Este turno está vigente en el momento dado? Maneja el cruce de
     * medianoche (turno Noche: 22:00-06:00 del día siguiente): si
     * hora_fin <= hora_inicio, se asume que el turno termina al día
     * siguiente de `fecha`.
     *
     * Sin esto, comparar hora_inicio <= momento <= hora_fin como horas
     * puras nunca es cierto entre las 00:00 y la hora_fin de un turno
     * que cruza medianoche.
     */
    public function cubre(Carbon $momento): bool
    {
        if ($this->es_descanso || ! $this->hora_inicio || ! $this->hora_fin) {
            return false;
        }

        $inicio = $this->fecha->copy()->setTimeFromTimeString($this->hora_inicio);
        $fin = $this->fecha->copy()->setTimeFromTimeString($this->hora_fin);

        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin->addDay();
        }

        return $momento->greaterThanOrEqualTo($inicio) && $momento->lessThanOrEqualTo($fin);
    }

    /**
     * Turno vigente de un usuario en un momento dado, considerando que
     * un turno que cruza medianoche (Noche) puede estar registrado con
     * `fecha` = el día anterior al que corresponde el momento consultado.
     * Reemplaza las consultas ad-hoc `whereDate('fecha', hoy)` que
     * fallaban para el turno Noche entre las 00:00 y las 06:00.
     */
    public static function vigenteParaUsuario(int $userId, ?Carbon $momento = null): ?self
    {
        $momento ??= now();

        return static::where('user_id', $userId)
            ->whereIn('fecha', [
                $momento->toDateString(),
                $momento->copy()->subDay()->toDateString(),
            ])
            ->get()
            ->first(fn (self $turno) => $turno->cubre($momento));
    }

    /**
     * Etiqueta corta para la vista calendario (equipo/individual):
     * 'D' para descanso, 'M'/'T'/'N' para régimen 728 (comparando
     * hora_inicio contra las horas vigentes de cada turno en
     * `configuraciones`) y 'DIA' para régimen 276. Solo para pintar la
     * grilla — nunca se usa para validar ni bloquear nada.
     */
    public function etiqueta(): string
    {
        if ($this->es_descanso || ! $this->hora_inicio) {
            return 'D';
        }

        return match ((string) $this->hora_inicio) {
            (string) Configuracion::valorDe('TURNO_MANANA_HORA_INICIO', '06:00') => 'M',
            (string) Configuracion::valorDe('TURNO_TARDE_HORA_INICIO', '14:00') => 'T',
            (string) Configuracion::valorDe('TURNO_NOCHE_HORA_INICIO', '22:00') => 'N',
            default => 'DIA',
        };
    }
}
