<?php

namespace App\Services;

use App\Models\CargaTurnoMensual;
use App\Models\Configuracion;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Arma el horario de un trabajador mes por mes a partir de su
 * ConfiguracionTurno vigente: N días de trabajo en su turno asignado
 * + M días de descanso, repitiendo en ciclo continuo (por defecto
 * 6 + 1, ver default en la migración).
 *
 * Clave del diseño: el ciclo se ancla a `fecha_ancla`, NUNCA se
 * reinicia el día 1 de cada mes. Por eso generar el mes siguiente
 * (tenga 28, 29, 30 o 31 días) es solo continuar la misma cuenta de
 * días desde el ancla — no hay "sobrante" que decidir ni patrón que
 * adaptar entre meses de distinto tamaño.
 *
 * "No pisar lo cargado" se resuelve con `cargas_turno_mensuales`: si
 * ya existe una fila para (trabajador, año, mes) -sea manual o
 * automática- el origen 'automatico' no regenera nada. Solo una
 * carga explícitamente 'manual' (vía cargarConfiguracion) puede
 * reemplazar un mes ya resuelto.
 */
class GeneradorTurnoMensualService
{
    /**
     * Punto de entrada para el Admin/Jefe (Livewire). Reemplaza la
     * configuración vigente del trabajador y regenera desde la fecha
     * ancla hasta fin de ese mes, marcando el mes como 'manual' para
     * que el comando automático no lo vuelva a tocar.
     */
    public function cargarConfiguracion(
        User $trabajador,
        string $turno,
        Carbon $fechaAncla,
        User $actor,
        int $diasTrabajo = 6,
        int $diasDescanso = 1,
    ): ConfiguracionTurno {
        if (! in_array($turno, ConfiguracionTurno::turnosValidosPara($trabajador), true)) {
            throw ValidationException::withMessages([
                'turno' => 'Ese turno no es válido para el régimen '.$trabajador->regimen.' de este trabajador.',
            ]);
        }

        if (! $trabajador->activo) {
            throw ValidationException::withMessages([
                'turno' => 'Este trabajador está marcado como inactivo; no se le puede asignar un turno.',
            ]);
        }

        $config = ConfiguracionTurno::updateOrCreate(
            ['user_id' => $trabajador->id],
            [
                'turno' => $turno,
                'fecha_ancla' => $fechaAncla->toDateString(),
                'dias_trabajo' => $diasTrabajo,
                'dias_descanso' => $diasDescanso,
                'actualizado_por_id' => $actor->id,
            ]
        );

        $this->generarMes($trabajador, $fechaAncla->copy()->startOfMonth(), $actor, 'manual');

        return $config;
    }

    /**
     * Genera (o regenera, si $origen es 'manual') los turnos de todo
     * el mes de $mesInicio para $trabajador, según su configuración
     * vigente. Si no tiene configuración, no hace nada — significa
     * que nadie le asignó nunca un turno rotativo.
     *
     * Si $trabajador->activo es false (se retiró, cese, licencia
     * larga) el automático NUNCA genera nada — el ciclo simplemente
     * deja de "correr" para él sin que nadie tenga que borrar su
     * ConfiguracionTurno. Un 'manual' explícito sí podría forzarlo
     * (ej. reincorporación), por eso el corte es solo para automático.
     */
    public function generarMes(
        User $trabajador,
        Carbon $mesInicio,
        ?User $actor,
        string $origen = 'automatico',
        ?ConfiguracionTurno $config = null,
    ): void {
        if ($origen === 'automatico' && ! $trabajador->activo) {
            return;
        }

        // Permite pasar la ConfiguracionTurno ya cargada (ver
        // generarProximoMesParaTodos, que ya la trae con ->with) en
        // vez de volver a consultarla por cada trabajador del batch.
        $config ??= ConfiguracionTurno::where('user_id', $trabajador->id)->first();

        if (! $config) {
            return;
        }

        $anio = $mesInicio->year;
        $mes = $mesInicio->month;

        // El automático nunca pisa un mes ya resuelto (manual o
        // automático previo). Solo una carga 'manual' explícita puede
        // regenerar un mes que ya tenía turnos. Queda como red de
        // seguridad para llamadas directas; generarProximoMesParaTodos
        // ya descarta antes estos casos con una sola query en batch.
        if ($origen === 'automatico') {
            $yaTieneCargaEsteMes = CargaTurnoMensual::where('user_id', $trabajador->id)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->exists();

            if ($yaTieneCargaEsteMes) {
                return;
            }
        }

        [$horaInicio, $horaFin] = $this->horasDe($config->turno);
        $ciclo = $config->dias_trabajo + $config->dias_descanso;

        $dia = $mesInicio->copy()->startOfMonth();
        $finDeMes = $mesInicio->copy()->endOfMonth();
        $ahora = now();
        $filas = [];

        while ($dia->lessThanOrEqualTo($finDeMes)) {
            // Módulo positivo: fecha_ancla puede ser posterior a $dia
            // (trabajador nuevo) o muy anterior, diffInDays no da
            // negativo pero se protege igual por claridad del cálculo.
            $offset = $config->fecha_ancla->diffInDays($dia, absolute: false);
            $posicion = (($offset % $ciclo) + $ciclo) % $ciclo;
            $esDescanso = $posicion >= $config->dias_trabajo;

            $filas[] = [
                'user_id' => $trabajador->id,
                'fecha' => $dia->toDateString(),
                'sede_id' => $trabajador->sede_id,
                'es_descanso' => $esDescanso,
                'hora_inicio' => $esDescanso ? null : $horaInicio,
                'hora_fin' => $esDescanso ? null : $horaFin,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];

            $dia->addDay();
        }

        // Un solo upsert para todo el mes (hasta 31 filas) en vez de
        // un updateOrCreate por día: con cientos de trabajadores 728
        // corriendo cada mes vía generarProximoMesParaTodos(), esto
        // evita miles de queries individuales. 'created_at' solo se
        // aplica al insertar filas nuevas (no está en el tercer
        // argumento), igual que hacía updateOrCreate.
        Turno::upsert(
            $filas,
            ['user_id', 'fecha'],
            ['sede_id', 'es_descanso', 'hora_inicio', 'hora_fin', 'updated_at']
        );

        CargaTurnoMensual::updateOrCreate(
            ['user_id' => $trabajador->id, 'anio' => $anio, 'mes' => $mes],
            [
                'origen' => $origen,
                'configuracion_turno_id' => $config->id,
                'generado_por_id' => $actor?->id,
            ]
        );
    }

    /**
     * Corre desde el comando programado: para cada trabajador activo
     * con configuración vigente, genera el mes siguiente si todavía
     * no tiene nada cargado para ese mes (ver guardia en generarMes).
     * Los inactivos (retiro, cese, licencia) se saltan directamente:
     * su ciclo queda "pausado" sin que nadie borre su configuración.
     */
    public function generarProximoMesParaTodos(): void
    {
        $proximoMes = now()->addMonthNoOverflow()->startOfMonth();

        $configs = ConfiguracionTurno::with('usuario')
            ->whereHas('usuario', fn ($q) => $q->where('activo', true))
            ->get();

        if ($configs->isEmpty()) {
            return;
        }

        // Una sola consulta para saber qué trabajadores ya tienen el
        // mes siguiente resuelto, en vez de una por trabajador dentro
        // de generarMes: en régimen estable (la mayoría de meses ya
        // generados) esto evita 2 queries redundantes por cada
        // trabajador activo con turno rotativo.
        $userIdsConCarga = CargaTurnoMensual::where('anio', $proximoMes->year)
            ->where('mes', $proximoMes->month)
            ->whereIn('user_id', $configs->pluck('user_id'))
            ->pluck('user_id')
            ->all();

        $configs
            ->reject(fn (ConfiguracionTurno $config) => in_array($config->user_id, $userIdsConCarga, true))
            ->each(function (ConfiguracionTurno $config) use ($proximoMes) {
                if ($config->usuario) {
                    $this->generarMes(
                        $config->usuario,
                        $proximoMes->copy(),
                        actor: null,
                        origen: 'automatico',
                        config: $config,
                    );
                }
            });
    }

    /**
     * Horas de cada turno del catálogo, parametrizadas en
     * `configuraciones` (nunca hardcodeadas, misma convención que
     * HorarioOrdinarioService). DIA reutiliza por defecto las mismas
     * horas del horario ordinario global de 276, pero es una clave
     * independiente porque esta tabla (`turnos`) es solo informativa
     * y no reemplaza la ventana que sí bloquea en HorarioOrdinarioService.
     *
     * @return array{0: string, 1: string} [hora_inicio, hora_fin] en H:i
     */
    private function horasDe(string $turno): array
    {
        return match ($turno) {
            'MANANA' => [
                (string) Configuracion::valorDe('TURNO_MANANA_HORA_INICIO', '06:00'),
                (string) Configuracion::valorDe('TURNO_MANANA_HORA_FIN', '14:00'),
            ],
            'TARDE' => [
                (string) Configuracion::valorDe('TURNO_TARDE_HORA_INICIO', '14:00'),
                (string) Configuracion::valorDe('TURNO_TARDE_HORA_FIN', '22:00'),
            ],
            'NOCHE' => [
                (string) Configuracion::valorDe('TURNO_NOCHE_HORA_INICIO', '22:00'),
                (string) Configuracion::valorDe('TURNO_NOCHE_HORA_FIN', '06:00'),
            ],
            default => [
                (string) Configuracion::valorDe('TURNO_DIA_HORA_INICIO', '07:45'),
                (string) Configuracion::valorDe('TURNO_DIA_HORA_FIN', '16:15'),
            ],
        };
    }
}
