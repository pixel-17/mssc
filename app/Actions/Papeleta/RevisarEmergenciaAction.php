<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\CalculadorDiasHabiles;
use App\Services\NotificarPapeletaService;
use Illuminate\Support\Facades\DB;

/**
 * Paso 6: Jefe y RRHH revisan la Emergencia en paralelo, cada uno con
 * su propio visto bueno — uno no pisa ni implica el del otro, y
 * ninguno bloquea al trabajador (la papeleta sigue su ciclo normal de
 * retorno/cierre mientras tanto).
 *
 * "Solo cambia el motivo, horas intactas": por eso observar acá NUNCA
 * mueve el estado de la papeleta ni AutorizadaYCorriendo ni Cerrada —
 * únicamente arma el plazo de subsanación (ver
 * ProcesarSubsanacionEmergenciaVencida, que sí puede reclasificar).
 */
class RevisarEmergenciaAction
{
    use ExigeDecisorAjeno;

    public function __construct(
        private NotificarPapeletaService $notificar,
        private CalculadorDiasHabiles $diasHabiles,
    ) {}

    public function aprobar(Papeleta $papeleta, User $revisor): Papeleta
    {
        return $this->resolver($papeleta, $revisor, 'aprobado');
    }

    public function observar(Papeleta $papeleta, User $revisor, string $comentario): Papeleta
    {
        return $this->resolver($papeleta, $revisor, 'observado', $comentario);
    }

    private function resolver(Papeleta $papeleta, User $revisor, string $resultado, ?string $comentario = null): Papeleta
    {
        [$papeleta, $rol] = DB::transaction(function () use ($papeleta, $revisor, $resultado, $comentario) {
            // Relectura bajo lock: jefe y RRHH revisan EN PARALELO la misma
            // fila; sin lock, el segundo guardado pisaba las columnas del
            // primero (visto bueno, plazo de subsanación) con datos viejos.
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $revisor);

            if (! $actual->es_emergencia) {
                throw new PapeletaException('Esta papeleta no es de Emergencia, no tiene revisión post-hoc doble.');
            }

            $rol = $this->rolDe($actual, $revisor);
            $columnaEstado = "visto_bueno_{$rol}_emergencia";

            if ($actual->{$columnaEstado} !== 'pendiente') {
                throw new PapeletaException('Ya diste tu visto bueno sobre esta Emergencia.');
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->{$columnaEstado} = $resultado;
            $actual->{"visto_bueno_{$rol}_emergencia_por_id"} = $revisor->id;
            $actual->{"visto_bueno_{$rol}_emergencia_at"} = now();

            // El plazo se fija en la PRIMERA observación (jefe o RRHH,
            // lo que ocurra antes) y no se reinicia si el otro observa
            // después — un solo reloj de subsanación por papeleta.
            if ($resultado === 'observado' && ! $actual->subsanacion_emergencia_fecha_limite) {
                $dias = (int) Configuracion::valorDe('SUBSANACION_EMERGENCIA_DIAS_HABILES', 15);
                $actual->subsanacion_emergencia_fecha_limite = $this->diasHabiles->agregarDiasHabiles(now(), $dias);
            }

            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $revisor->id,
                'actor_tipo' => $rol === 'rrhh' ? 'rrhh' : 'jefe_inmediato',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado), // no cambia, ver docblock
                'justificacion' => $comentario ?? "Visto bueno de Emergencia ({$rol}): {$resultado}.",
            ]);

            return [$actual, $rol];
        });

        if ($resultado === 'observado') {
            $this->notificar->emergenciaObservada($papeleta, $rol);
        }

        return $papeleta;
    }

    private function rolDe(Papeleta $papeleta, User $revisor): string
    {
        if ($revisor->hasRole('rrhh')) {
            return 'rrhh';
        }

        if ($revisor->esJefeInmediatoDe($papeleta->trabajador)) {
            return 'jefe';
        }

        throw new PapeletaException('No tienes permiso para revisar esta Emergencia.');
    }
}
