<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\Retorno;
use App\Models\Sustento;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\FinalizadoSinRetorno;
use App\States\Papeleta\RetornoPendienteSustento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paso 5: cierra el ciclo operativo del trabajador.
 *
 * Evidencia normal: foto + GPS + hora del servidor simultáneos.
 * `dentro_de_radio` es solo una bandera informativa (se calcula contra
 * el radio de la sede) — el documento NO la usa para bloquear el
 * retorno, solo para que RRHH/jefe la vean en la revisión.
 *
 * Excepción de conectividad: sin foto/GPS, marcado por el jefe
 * inmediato, con justificación obligatoria (ver marcarManual()).
 *
 * Cierre según motivo:
 * - requiere_sustento_en_retorno (Salud): SIEMPRE pasa por
 *   RETORNO_PENDIENTE_SUSTENTO, incluso si ya viene con archivo — un
 *   adjunto nunca cierra el caso por sí solo (Paso 8), necesita visto
 *   bueno humano vía SustentoAction (pendiente de implementar).
 * - Cualquier otro motivo (Particular, Comisión con retorno físico):
 *   retorno normal -> CERRADA directo.
 *
 * El refrigerio (Paso 7) se calcula siempre al cerrar, comparando la
 * ausencia real (hora_salida_real -> hora_servidor del retorno) contra
 * el bloque de almuerzo configurable, y solo si el régimen es 276.
 */
class MarcarRetornoAction
{
    public function normal(Papeleta $papeleta, User $trabajador, array $datos): Papeleta
    {
        if ($papeleta->trabajador_id !== $trabajador->id) {
            throw new PapeletaException('No puedes marcar el retorno de una papeleta que no es tuya.');
        }

        return $this->ejecutar($papeleta, [
            'foto_path' => $datos['foto_path'] ?? null,
            'latitud' => $datos['latitud'] ?? null,
            'longitud' => $datos['longitud'] ?? null,
            'hora_servidor' => now(),
            'dentro_de_radio' => $this->calcularDentroDeRadio($papeleta, $datos),
            'marcado_manual' => false,
        ]);
    }

    /**
     * Falla de conectividad: lo marca el jefe inmediato, sin foto/GPS,
     * con justificación obligatoria. Dispara alerta posterior a RRHH
     * (revision_posthoc_estado ya cubre esa cola; se reutiliza aquí en
     * vez de crear un canal de alerta paralelo).
     */
    public function manual(Papeleta $papeleta, User $jefe, string $justificacion): Papeleta
    {
        if (! $jefe->esJefeInmediatoDe($papeleta->trabajador)) {
            throw new PapeletaException('Solo un jefe inmediato del trabajador puede marcar un retorno manual por falla de conectividad.');
        }

        if (trim($justificacion) === '') {
            throw new PapeletaException('La justificación es obligatoria para un retorno manual.');
        }

        return $this->ejecutar($papeleta, [
            'foto_path' => null,
            'latitud' => null,
            'longitud' => null,
            'hora_servidor' => now(),
            'dentro_de_radio' => null,
            'marcado_manual' => true,
            'marcado_manual_por_id' => $jefe->id,
            'justificacion_manual' => $justificacion,
        ]);
    }

    private function ejecutar(Papeleta $papeleta, array $datosRetorno): Papeleta
    {
        if (! $papeleta->estado->equals(AutorizadaYCorriendo::class)) {
            throw new PapeletaException('Esta papeleta no está en curso, no se puede marcar el retorno.');
        }

        if ($papeleta->retorno()->exists()) {
            throw new PapeletaException('Esta papeleta ya tiene un retorno registrado.');
        }

        return DB::transaction(function () use ($papeleta, $datosRetorno) {
            $estadoAnterior = class_basename($papeleta->estado);

            $retorno = Retorno::create(array_merge(['papeleta_id' => $papeleta->id], $datosRetorno));

            $papeleta->descuento_refrigerio_minutos = $this->calcularDescuentoRefrigerio($papeleta, $retorno->hora_servidor);

            if ($papeleta->motivo->requiere_sustento_en_retorno) {
                $papeleta->estado = new RetornoPendienteSustento($papeleta);

                $horasHabiles = (int) Configuracion::valorDe('SUSTENTO_HORAS_HABILES', 48);
                Sustento::create([
                    'papeleta_id' => $papeleta->id,
                    'fecha_limite' => app(\App\Services\CalculadorDiasHabiles::class)
                        ->agregarHorasHabiles($retorno->hora_servidor->copy(), $horasHabiles),
                    'estado' => 'pendiente',
                ]);
            } else {
                $papeleta->estado = new Cerrada($papeleta);
            }

            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $datosRetorno['marcado_manual_por_id'] ?? $papeleta->trabajador_id,
                'actor_tipo' => $datosRetorno['marcado_manual'] ? 'jefe_inmediato' : 'trabajador',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $datosRetorno['justificacion_manual'] ?? null,
            ]);

            return $papeleta;
        });
    }

    /**
     * Comisión de Servicio sin retorno físico: no pasa por acá (nunca
     * hay evidencia de retorno), se cierra directo con visto bueno
     * humano. FINALIZADO_SIN_RETORNO con causa distinta al abandono
     * del job de vencimiento — motivo por el que vive en su propio
     * método y no en ejecutar().
     */
    public function cerrarSinRetornoFisico(Papeleta $papeleta, User $quienConfirma): Papeleta
    {
        if (! $papeleta->estado->equals(AutorizadaYCorriendo::class)) {
            throw new PapeletaException('Esta papeleta no está en curso.');
        }

        if (! $papeleta->motivo->permite_cierre_sin_retorno) {
            throw new PapeletaException('El motivo de esta papeleta no permite cierre sin retorno físico.');
        }

        return DB::transaction(function () use ($papeleta, $quienConfirma) {
            $estadoAnterior = class_basename($papeleta->estado);

            $papeleta->estado = new FinalizadoSinRetorno($papeleta);
            $papeleta->causa_finalizacion_sin_retorno = 'comision_servicio_campo';
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $quienConfirma->id,
                'actor_tipo' => $quienConfirma->esJefeInmediatoDe($papeleta->trabajador) ? 'jefe_inmediato' : 'rrhh',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => 'Visto bueno humano: comisión de servicio cerrada sin retorno físico.',
            ]);

            return $papeleta;
        });
    }

    private function calcularDentroDeRadio(Papeleta $papeleta, array $datos): ?bool
    {
        if (empty($datos['latitud']) || empty($datos['longitud'])) {
            return null;
        }

        $sede = $papeleta->sede;

        if (! $sede?->latitud || ! $sede?->longitud) {
            return null;
        }

        $distanciaMetros = $this->distanciaHaversine(
            (float) $sede->latitud,
            (float) $sede->longitud,
            (float) $datos['latitud'],
            (float) $datos['longitud'],
        );

        return $distanciaMetros <= $sede->radio_metros;
    }

    private function distanciaHaversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radioTierraMetros = 6371000;

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLon / 2) ** 2;

        return $radioTierraMetros * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Paso 7: solo 276 descuenta refrigerio. Se compara el solapamiento
     * real de la ausencia contra el bloque de almuerzo configurable
     * (BLOQUE_ALMUERZO_INICIO / BLOQUE_ALMUERZO_FIN, HH:MM). Si el
     * bloque no cae dentro de la ausencia, el descuento es 0.
     */
    private function calcularDescuentoRefrigerio(Papeleta $papeleta, Carbon $horaRetorno): int
    {
        if ($papeleta->regimen !== '276' || ! $papeleta->hora_salida_real) {
            return 0;
        }

        $inicioAlmuerzo = Configuracion::valorDe('BLOQUE_ALMUERZO_INICIO', '13:00');
        $finAlmuerzo = Configuracion::valorDe('BLOQUE_ALMUERZO_FIN', '14:00');

        $bloqueInicio = $papeleta->hora_salida_real->copy()
            ->setTimeFromTimeString($inicioAlmuerzo);
        $bloqueFin = $papeleta->hora_salida_real->copy()
            ->setTimeFromTimeString($finAlmuerzo);

        $solapeInicio = $papeleta->hora_salida_real->max($bloqueInicio);
        $solapeFin = $horaRetorno->min($bloqueFin);

        if ($solapeInicio->greaterThanOrEqualTo($solapeFin)) {
            return 0;
        }

        return $solapeInicio->diffInMinutes($solapeFin);
    }
}
