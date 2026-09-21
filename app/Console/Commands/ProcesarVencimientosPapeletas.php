<?php

namespace App\Console\Commands;

use App\Actions\Papeleta\RrhhHorarioService;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Services\DeterminadorFinDeTurno;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Vencida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Corre cada minuto (ver routes/console.php). Cubre la parte del flujo
 * que NO depende de que un humano haga clic:
 *
 * 1) Vencimiento: cualquier papeleta no terminal cuyo turno/día ya terminó
 *    sin que nadie decidiera -> VENCIDA. El sistema nunca autoriza por
 *    inacción (Paso 2). Decidir sigue siendo SIEMPRE responsabilidad del
 *    Jefe Inmediato: si no decide a tiempo, la papeleta no escala a nadie,
 *    simplemente espera hasta vencer.
 * 2) Cierre de RRHH: papeletas que el jefe ya aprobó y esperan en
 *    PENDIENTE_RRHH cuando RRHH sale de horario -> AUTORIZADA_Y_CORRIENDO con
 *    revisión post-hoc pendiente (mismo carril que AprobarJefeAction cuando
 *    RRHH ya estaba fuera de horario). La decisión de fondo la tomó el jefe;
 *    lo único que cambia es que RRHH revisa después en vez de antes.
 *
 * El paso 5 (sustento 48h) usa la misma idea pero se implementa en un
 * comando aparte para no mezclar ventanas de tiempo distintas en un solo
 * método gigante.
 */
class ProcesarVencimientosPapeletas extends Command
{
    protected $signature = 'papeletas:procesar-vencimientos';

    protected $description = 'Vence las papeletas que ya no tienen turno/día vigente y autoriza (con revisión post-hoc) las aprobadas por el jefe que esperan a RRHH cuando RRHH sale de horario.';

    public function __construct(
        private DeterminadorFinDeTurno $finDeTurno,
        private NotificarPapeletaService $notificar,
        private RrhhHorarioService $horarioRrhh,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->vencerPapeletasSinTurnoVigente();
        // Después de vencer: una papeleta cuyo turno ya terminó debe quedar
        // Vencida, no autorizada.
        $this->autorizarPendientesDeRrhhFueraDeHorario();

        return self::SUCCESS;
    }

    /**
     * El jefe aprobó cuando RRHH aún contaba como "en horario" (p. ej. a las
     * 16:15) y RRHH salió antes de decidir: la papeleta quedaba en
     * PENDIENTE_RRHH sin nadie que la resolviera hasta que vencía. Aquí se
     * pasa al carril "RRHH fuera de horario": el trabajador puede salir y
     * RRHH revisa después (post-hoc obligatoria).
     */
    private function autorizarPendientesDeRrhhFueraDeHorario(): void
    {
        if ($this->horarioRrhh->estaEnHorarioAhora()) {
            return;
        }

        Papeleta::whereState('estado', PendienteRrhh::class)
            ->whereNotNull('jefe_resuelto_at')
            ->chunkById(100, function ($lote) {
                foreach ($lote as $candidata) {
                    try {
                        $this->autorizarPorCierreDeRrhh($candidata);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function autorizarPorCierreDeRrhh(Papeleta $candidata): void
    {
        $papeleta = DB::transaction(function () use ($candidata) {
            // Relectura bajo lock: RRHH pudo decidir (aprobar, observar,
            // rechazar) entre la lectura del lote y aquí.
            $actual = Papeleta::whereKey($candidata->id)->lockForUpdate()->first();

            if (! $actual
                || ! $actual->estado->equals(PendienteRrhh::class)
                || $this->finDeTurno->yaTermino($actual)) {
                return null;
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->transicionarA(AutorizadaYCorriendo::class);
            $actual->autorizado_con_rrhh_fuera_horario = true;
            $actual->hora_salida_real = now();
            $actual->revision_posthoc_estado = 'pendiente';
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => null,
                'actor_tipo' => 'sistema',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => 'RRHH salió de horario con la papeleta ya aprobada por el jefe: se autoriza y queda pendiente de revisión post-hoc.',
            ]);

            return $actual;
        });

        if ($papeleta) {
            $this->notificar->puedeSalir($papeleta);
            $this->notificar->revisionPosthocPendiente($papeleta);
        }
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
