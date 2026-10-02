<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración vigente del ciclo de turno de UN trabajador. Ver
 * migración para el detalle de por qué el ciclo se ancla a una fecha
 * en vez de al calendario.
 *
 * Catálogo de turnos válidos, fijo por régimen (no editable por el
 * usuario final, ver GeneradorTurnoMensualService::horasDe para las
 * horas de cada uno):
 * - 728 (rotativo): MANANA, TARDE o NOCHE — el Admin/Jefe elige cuál.
 * - 276 (ordinario): solo DIA — no hay Tarde/Noche para este régimen.
 */
class ConfiguracionTurno extends Model
{
    protected $table = 'configuraciones_turno';

    protected $fillable = [
        'user_id',
        'turno',
        'fecha_ancla',
        'dias_trabajo',
        'dias_descanso',
        'actualizado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ancla' => 'date',
        ];
    }

    public const TURNOS_728 = ['MANANA', 'TARDE', 'NOCHE'];

    public const TURNO_276 = 'DIA';

    /**
     * user_id con turno rotativo configurado, agrupados por turno, en UNA
     * sola query: ['MANANA' => [12 => true], 'TARDE' => [...], 'NOCHE' => [...]].
     *
     * Existe para no consultar `configuraciones_turno` una vez por turno
     * y por unidad al pintar el organigrama o los avisos de "falta jefe":
     * se calcula una vez para todos los jefes involucrados y se pasa a
     * UnidadOrganica::turnosSinJefe(). Sin $userIds no hay query.
     *
     * @param  iterable<int>  $userIds  usuarios a considerar (los jefes inmediatos)
     * @return array<string, array<int, true>>
     */
    public static function idsPorTurno(iterable $userIds): array
    {
        $mapa = array_fill_keys(self::TURNOS_728, []);
        $ids = collect($userIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $mapa;
        }

        static::query()
            ->whereIn('turno', self::TURNOS_728)
            ->whereIn('user_id', $ids)
            ->get(['user_id', 'turno'])
            ->each(function (self $config) use (&$mapa) {
                $mapa[$config->turno][(int) $config->user_id] = true;
            });

        return $mapa;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    /**
     * Turnos que puede elegir el Admin/Jefe para este trabajador,
     * según su régimen. Fuente única de verdad de la regla "276 solo
     * tiene Día" — evitar repetir este if en el form y en el servicio.
     *
     * @return array<int, string>
     */
    public static function turnosValidosPara(User $trabajador): array
    {
        return $trabajador->regimen === '728' ? self::TURNOS_728 : [self::TURNO_276];
    }

    /** Nombre completo de un código de turno, para mensajes al usuario. */
    public static function etiquetaDeTurno(?string $turno): string
    {
        return match ($turno) {
            'MANANA' => 'Mañana',
            'TARDE' => 'Tarde',
            'NOCHE' => 'Noche',
            self::TURNO_276 => 'Día',
            default => (string) $turno,
        };
    }
}
