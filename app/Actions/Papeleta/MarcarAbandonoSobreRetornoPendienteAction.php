<?php

namespace App\Actions\Papeleta;

use App\Actions\Papeleta\Concerns\ExigeDecisorAjeno;
use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\FinalizadoSinRetorno;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Support\Facades\DB;

/**
 * "Abandono + sustento vencido simultáneos -> gana el abandono."
 *
 * Cubre el caso en que una papeleta ya tiene un Retorno registrado
 * (por eso está en RETORNO_PENDIENTE_SUSTENTO, no en
 * AUTORIZADA_Y_CORRIENDO) pero el jefe o RRHH determinan que ese
 * retorno no fue real — el job automático (ProcesarAbandonoNoMarcado)
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

            if (! $actual->estado->equals(RetornoPendienteSustento::class)) {
                throw new PapeletaException('Esta acción solo aplica a papeletas en espera de sustento.');
            }

            $estadoAnterior = class_basename($actual->estado);

            $actual->transicionarA(FinalizadoSinRetorno::class);
            $actual->causa_finalizacion_sin_retorno = 'abandono_no_marcado';
            $actual->requiere_visto_bueno = false; // la decisión humana ya se tomó acá
            $actual->save();

            HistorialPapeleta::create([
                'papeleta_id' => $actual->id,
                'actor_id' => $quienDecide->id,
                'actor_tipo' => $quienDecide->hasRole('rrhh') ? 'rrhh' : 'jefe_inmediato',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($actual->estado),
                'justificacion' => $justificacion,
            ]);

            return $actual;
        });

        $this->notificar->abandonoNoMarcado($papeleta);

        return $papeleta;
    }
}
