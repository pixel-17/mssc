<?php

namespace App\Console\Commands;

use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Turno;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Vencida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

    public function handle(): int
    {
        $this->escalarAJefeDeArea();
        $this->vencerPapeletasSinTurnoVigente();

        return self::SUCCESS;
    }

    private function escalarAJefeDeArea(): void
    {
        $slaMinutos = (int) Configuracion::valorDe('SLA_JEFE_MINUTOS', 5);

        Papeleta::whereState('estado', PendienteJefe::class)
            ->whereNull('escalado_jefe_area_at')
            ->whereNull('jefe_resuelto_at')
            ->where('created_at', '<=', now()->subMinutes($slaMinutos))
            ->whereNotNull('jefe_area_id')
            ->each(function (Papeleta $papeleta) {
                if ($this->actorEstaEnHorario($papeleta->jefe_area_id)) {
                    DB::transaction(function () use ($papeleta) {
                        $papeleta->escalado_jefe_area_at = now();
                        $papeleta->save();

                        HistorialPapeleta::create([
                            'papeleta_id' => $papeleta->id,
                            'actor_id' => null,
                            'actor_tipo' => 'sistema',
                            'estado_anterior' => class_basename($papeleta->estado),
                            'estado_nuevo' => class_basename($papeleta->estado), // no cambia de estado, solo de responsable
                            'justificacion' => 'Escalado automático: Jefe Inmediato no respondió dentro del SLA.',
                        ]);
                    });
                }
                // Si el Jefe de Área NO está en horario: no se hace nada.
                // La papeleta sigue en PENDIENTE_JEFE, esperando, sin alerta
                // adicional, tal como se definió en el flujo.
            });
    }

    /**
     * "Está en su horario" se valida SIEMPRE contra la propia fila de
     * `turnos` del actor para el día de hoy — nunca contra el horario de
     * otro actor ni contra parámetros globales.
     */
    private function actorEstaEnHorario(int $userId): bool
    {
        return Turno::where('user_id', $userId)
            ->whereDate('fecha', now()->toDateString())
            ->where('es_descanso', false)
            ->whereTime('hora_inicio', '<=', now())
            ->whereTime('hora_fin', '>=', now())
            ->exists();
    }

    private function vencerPapeletasSinTurnoVigente(): void
    {
        Papeleta::whereState('estado', $this->estadosNoTerminalesPreAutorizacion())
            ->each(function (Papeleta $papeleta) {
                if ($this->turnoDelDiaYaTermino($papeleta)) {
                    DB::transaction(function () use ($papeleta) {
                        $estadoAnterior = class_basename($papeleta->estado);

                        $papeleta->estado = new Vencida($papeleta);
                        $papeleta->vencida_at = now();
                        $papeleta->save();

                        HistorialPapeleta::create([
                            'papeleta_id' => $papeleta->id,
                            'actor_id' => null,
                            'actor_tipo' => 'sistema',
                            'estado_anterior' => $estadoAnterior,
                            'estado_nuevo' => class_basename($papeleta->estado),
                            'justificacion' => 'Vencida automáticamente: fin de turno/día sin decisión.',
                        ]);
                    });
                }
            });
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

    private function turnoDelDiaYaTermino(Papeleta $papeleta): bool
    {
        if ($papeleta->regimen === '728') {
            // 728 no tiene ventana de horario: el "fin de día" se toma
            // como el cierre del día operativo a medianoche.
            return now()->toDateString() > $papeleta->dia_operativo->toDateString();
        }

        $turno = Turno::where('user_id', $papeleta->trabajador_id)
            ->whereDate('fecha', $papeleta->dia_operativo)
            ->first();

        if (! $turno || ! $turno->hora_fin) {
            // Sin turno cargado, se usa fin de día como respaldo.
            return now()->toDateString() > $papeleta->dia_operativo->toDateString();
        }

        return now()->toDateString() > $papeleta->dia_operativo->toDateString()
            || now()->format('H:i:s') > $turno->hora_fin;
    }
}
