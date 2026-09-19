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
    protected static function booted(): void
    {
        // Tiempo real para altas/ediciones/bajas individuales (formulario
        // de turno, botón eliminar): centralizado acá para que cualquier
        // escritura futura de un solo turno lo avise sin acordarse.
        //
        // OJO: esto NO cubre Turno::upsert() ni bajas por query — son
        // consultas masivas (ProgramacionTurnoService, GeneradorTurno-
        // MensualService, por rendimiento con cientos de trabajadores) y
        // Eloquent no dispara eventos de modelo para ellas. Esos dos
        // servicios siguen notificando a mano después de escribir.
        static::saved(function (self $turno) {
            $antes = $turno->wasChanged('user_id') ? (int) $turno->getOriginal('user_id') : null;

            \App\Events\HorarioActualizado::notificar(...array_filter([$turno->user_id, $antes]));
        });

        static::deleted(fn (self $turno) => \App\Events\HorarioActualizado::notificar($turno->user_id));
    }

    protected $fillable = [
        'user_id',
        'sede_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'es_descanso',
        'turno',
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
     * Código del tipo de turno: 'DESCANSO', 'MANANA', 'TARDE', 'NOCHE'
     * o 'DIA' (276). Usa la columna `turno` cuando existe; para filas
     * antiguas (NULL) lo deduce comparando hora_inicio contra las horas
     * vigentes de cada turno en `configuraciones`.
     */
    public function codigo(): string
    {
        if ($this->es_descanso || ! $this->hora_inicio) {
            return 'DESCANSO';
        }

        if ($this->turno) {
            return $this->turno;
        }

        return match ((string) $this->hora_inicio) {
            (string) Configuracion::valorDe('TURNO_MANANA_HORA_INICIO', '06:00') => 'MANANA',
            (string) Configuracion::valorDe('TURNO_TARDE_HORA_INICIO', '14:00') => 'TARDE',
            (string) Configuracion::valorDe('TURNO_NOCHE_HORA_INICIO', '22:00') => 'NOCHE',
            default => 'DIA',
        };
    }

    /**
     * Etiqueta corta para la vista calendario (equipo/individual):
     * 'D' descanso, 'M'/'T'/'N' para régimen 728 y 'DIA' para 276.
     * Solo para pintar la grilla — nunca se usa para validar ni
     * bloquear nada.
     */
    public function etiqueta(): string
    {
        return match ($this->codigo()) {
            'DESCANSO' => 'D',
            'MANANA' => 'M',
            'TARDE' => 'T',
            'NOCHE' => 'N',
            default => 'DIA',
        };
    }

    /** Nombre completo del tipo de turno, para tooltips/leyendas (ver etiqueta()). */
    public function nombreTurno(): string
    {
        return match ($this->etiqueta()) {
            'M' => 'Mañana',
            'T' => 'Tarde',
            'N' => 'Noche',
            'D' => 'Descanso',
            default => 'Horario ordinario',
        };
    }

    /**
     * Clases Tailwind (claro + oscuro) para pintar la celda/badge de este
     * turno en el calendario, una por tipo (ver etiqueta()). Centralizado
     * acá para que la vista de equipo y la individual usen siempre la
     * misma paleta sin duplicar el match.
     */
    public function claseColor(): string
    {
        return \App\Support\TurnoColores::para($this->etiqueta());
    }
}
