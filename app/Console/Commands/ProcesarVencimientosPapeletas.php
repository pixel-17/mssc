<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Turno;
use App\Models\User;
use App\Services\DeterminadorFinDeTurno;
use App\Services\HorarioOrdinarioService;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Vencida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Corre cada minuto (ver routes/console.php). Cubre la parte del flujo
 * que NO depende de que un humano haga clic:
 *
 * 1) Escalamiento: papeletas en PENDIENTE_JEFE cuyo reloj (SLA_JEFE_MINUTOS)
 *    venció sin respuesta -> si el Jefe de Área está en su propio horario
 *    (su fila en `turnos` de hoy), se marca escalado_jefe_area_at. Si no,
 *    NO se escala: la papeleta simplemente sigue esperando.
 * 2) Vencimiento: cualquier papeleta no terminal cuyo turno/día ya terminó
 *    sin que nadie decidiera -> VENCIDA. El sistema nunca autoriza por
 *    inacción (Paso 2).
 *
 * Los pasos 3 (RRHH), 5 (sustento 48h) y 6 (subsanación Emergencia) usan
 * la misma idea pero se implementan en comandos aparte para no mezclar
 * ventanas de tiempo distintas en un solo método gigante.
 */
class ProcesarVencimientosPapeletas extends Command
{
    protected $signature = 'papeletas:procesar-vencimientos';

    protected $description = 'Escala al Jefe de Área las papeletas cuyo SLA de jefe venció, y vence las que ya no tienen turno/día vigente.';

    public function __construct(
        private DeterminadorFinDeTurno $finDeTurno,
        private NotificarPapeletaService $notificar,
        private HorarioOrdinarioService $horarioOrdinario,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->escalarAJefeDeArea();
        $this->vencerPapeletasSinTurnoVigente();

        return self::SUCCESS;
    }

    private function escalarAJefeDeArea(): void
    {
        $slaMinutos = (int) Configuracion::valorDe('SLA_JEFE_MINUTOS', 5);

        // chunkById (no each()): each() pagina por OFFSET y este conjunto
        // se va vaciando mientras se procesa, así que saltaba filas.
        // Cargar jefeArea evita un User::find() repetido por papeleta.
        Papeleta::whereState('estado', PendienteJefe::class)
            ->whereNull('escalado_jefe_area_at')
            ->whereNull('jefe_resuelto_at')
            ->where('created_at', '<=', now()->subMinutes($slaMinutos))
            ->whereNotNull('jefe_area_id')
            ->with('jefeArea')
            ->chunkById(100, function ($lote) {
                foreach ($lote as $candidata) {
                    // Un fallo en una papeleta no debe tumbar el resto del ciclo.
                    try {
                        $this->escalar($candidata);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function escalar(Papeleta $candidata): void
    {
        // Si el Jefe de Área NO está en horario no se hace nada: la
        // papeleta sigue en PENDIENTE_JEFE esperando, sin alerta extra.
        if (! $this->actorEstaEnHorario($candidata->jefeArea)) {
            return;
        }

        $papeleta = DB::transaction(function () use ($candidata) {
            // Se relee bajo lock: entre la lectura del lote y aquí el jefe
            // pudo decidir, o la papeleta pudo cancelarse.
            $actual = Papeleta::whereKey($candidata->id)->lockForUpdate()->first();

            if (! $actual
                || ! $actual->estado->equals(PendienteJefe::class)
                || $actual->escalado_jefe_area_at !== null
                || $actual->jefe_resuelto_at !== null) {
                return null;
            }

            $actual->escalado_jefe_area_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => null,
                'actor_tipo' => 'sistema',
                'estado_anterior' => class_basename($actual->estado),
                'estado_nuevo' => class_basename($actual->estado), // no cambia de estado, solo de responsable
                'justificacion' => 'Escalado automático: Jefe Inmediato no respondió dentro del SLA.',
            ]);

            return $actual;
        });

        if ($papeleta) {
            $this->notificar->escaladaAJefeDeArea($papeleta);
        }
    }

    /**
     * Memoiza por user_id el resultado de "está en su turno vigente"
     * dentro de esta misma corrida del comando (cada minuto): si el
     * mismo Jefe de Área tiene varias papeletas escalando a la vez,
     * evita repetir la consulta a `turnos` para el mismo usuario.
     *
     * @var array<int, bool>
     */
    private array $cacheEnHorario728 = [];

    /**
     * "Está en su horario" se valida SIEMPRE contra el propio régimen del
     * actor — nunca contra el horario de otro actor:
     * - 276 (ordinario): horario único global (HorarioOrdinarioService),
     *   igual que para el trabajador que crea la papeleta.
     * - 728 (rotativo): sigue contra su propia fila de `turnos` de hoy
     *   (opcional/informativa; si no la cargaron, no se considera "en
     *   horario" para efectos de escalar).
     */
    private function actorEstaEnHorario(?User $actor): bool
    {
        if ($actor?->regimen === '728') {
            // Turno::vigenteParaUsuario maneja el cruce de medianoche del
            // turno Noche (22:00-06:00 del día siguiente); antes, entre
            // 00:00 y 06:00 esto nunca detectaba al actor como "en turno".
            return $this->cacheEnHorario728[$actor->id] ??= Turno::vigenteParaUsuario($actor->id, now()) !== null;
        }

        return $this->horarioOrdinario->estaDentroDeVentana();
    }

    private function vencerPapeletasSinTurnoVigente(): void
    {
        Papeleta::whereState('estado', $this->estadosNoTerminalesPreAutorizacion())
            ->chunkById(100, function ($lote) {
                foreach ($lote as $candidata) {
                    if (! $this->finDeTurno->yaTermino($candidata)) {
                        continue;
                    }

                    try {
                        $this->vencer($candidata);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function vencer(Papeleta $candidata): void
    {
        $papeleta = DB::transaction(function () use ($candidata) {
            // Sin esta relectura, un jefe que aprobaba entre la lectura y
            // el guardado quedaba pisado como Vencida.
            $actual = Papeleta::whereKey($candidata->id)->lockForUpdate()->first();

            if (! $actual
                || ! $actual->estado->equals(...$this->estadosNoTerminalesPreAutorizacion())
                || ! $this->finDeTurno->yaTermino($actual)) {
                return null;
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->transicionarA(Vencida::class);
            $actual->vencida_at = now();
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => null,
                'actor_tipo' => 'sistema',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => 'Vencida automáticamente: fin de turno/día sin decisión.',
            ]);

            return $actual;
        });

        if ($papeleta) {
            $this->notificar->vencida($papeleta);
        }
    }

    /**
     * @return array<class-string>
     */
    private function estadosNoTerminalesPreAutorizacion(): array
    {
        return [
            \App\States\Papeleta\PendienteJefe::class,
            \App\States\Papeleta\ObservadaPorJefe::class,
            \App\States\Papeleta\PendienteRrhh::class,
            \App\States\Papeleta\ObservadaPorRrhh::class,
        ];
    }

}
