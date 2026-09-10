<?php

namespace App\Actions\Papeleta;

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
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $quienDecide, string $justificacion): Papeleta
    {
        if (! $papeleta->estado->equals(RetornoPendienteSustento::class)) {
            throw new PapeletaException('Esta acción solo aplica a papeletas en espera de sustento.');
        }

        if (trim($justificacion) === '') {
            throw new PapeletaException('La justificación es obligatoria para marcar abandono sobre un retorno ya registrado.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $quienDecide, $justificacion) {
            $estadoAnterior = class_basename($papeleta->estado);

            $papeleta->estado = new FinalizadoSinRetorno($papeleta);
            $papeleta->causa_finalizacion_sin_retorno = 'abandono_no_marcado';
            $papeleta->requiere_visto_bueno = false; // la decisión humana ya se tomó acá
            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $quienDecide->id,
                'actor_tipo' => $quienDecide->hasRole('rrhh') ? 'rrhh' : 'jefe_inmediato',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $justificacion,
            ]);

            return $papeleta;
        });

        $this->notificar->abandonoNoMarcado($papeleta);

        return $papeleta;
    }
}
