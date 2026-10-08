<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Illuminate\Support\Facades\DB;

/**
 * "Abandono + sustento vencido simultáneos -> gana el abandono."
 *
 * Cubre el caso en que una papeleta ya tiene un Retorno registrado
 * (por eso está EnJustificacion, no AutorizadaYCorriendo) pero el jefe o
 * RRHH determinan que ese retorno no fue real. Termina en Finalizada
 * (con descuento): el motivo pasa al destino de reclasificación para que
 * el descuento se calcule, y la justificación abierta se da por vencida — el job automático (ProcesarAbandonoNoMarcado)
 * nunca decide esto solo, porque exige un juicio humano explícito
 * sobre evidencia ya registrada. De ahí que sea un Action separado y
 * no una rama más del job de vencimientos.
 */
class MarcarAbandonoSobreRetornoPendienteAction
{
    use ExigeDecisorAjeno;

    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $quienDecide, string $justificacion): Papeleta
    {
        if (trim($justificacion) === '') {
            throw new PapeletaException('La justificación es obligatoria para marcar abandono sobre un retorno ya registrado.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $quienDecide, $justificacion) {
            // Relectura bajo lock (mismo criterio que AprobarJefeAction): el
            // $papeleta que llega puede estar desactualizado si otro
            // proceso (el trabajador subiendo sustento, un job) lo movió.
            /** @var Papeleta $actual */
            $actual = Papeleta::whereKey($papeleta->id)->lockForUpdate()->firstOrFail();

            $this->exigirDecisorAjeno($actual, $quienDecide);

            if (! $actual->estado->equals(EnJustificacion::class)) {
                throw new PapeletaException('Esta acción solo aplica a papeletas en justificación.');
            }

            if (! $actual->retorno()->exists()) {
                throw new PapeletaException('Esta papeleta ya está marcada como abandono: no tiene un retorno registrado que descartar.');
            }

            $motivoParticular = Motivo::where('es_destino_reclasificacion', true)->first();

            if (! $motivoParticular) {
                throw new PapeletaException('No hay un motivo configurado como destino de reclasificación (es_destino_reclasificacion).');
            }

            $estadoAnterior = class_basename($actual->estado);

            $motivoAnteriorId = $actual->motivo_id;

            if ($motivoAnteriorId !== $motivoParticular->id) {
                $actual->motivo_original_id = $motivoAnteriorId;
                $actual->motivo_id = $motivoParticular->id;
            }

            // La justificación abierta ya no tiene dónde presentarse.
            Sustento::where('papeleta_id', $actual->id)
                ->whereIn('estado', ['pendiente', 'presentado'])
                ->update(['estado' => 'vencido']);

            $actual->transicionarA(Finalizada::class);
            $actual->causa_finalizacion_sin_retorno = 'abandono_no_marcado';
            $actual->requiere_visto_bueno = false; // la decisión humana ya se tomó acá
            $actual->regularizacion_fecha_limite = null;
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $quienDecide->id,
                'actor_tipo' => $quienDecide->hasRole('rrhh') ? 'rrhh' : 'jefe_inmediato',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'motivo_anterior_id' => $motivoAnteriorId,
                'motivo_nuevo_id' => $actual->motivo_id,
                'justificacion' => $justificacion,
            ]);

            return $actual;
        });

        $this->notificar->abandonoNoMarcado($papeleta);

        return $papeleta;
    }
}
