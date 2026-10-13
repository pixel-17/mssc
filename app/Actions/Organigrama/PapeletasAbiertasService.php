<?php

namespace App\Actions\Organigrama;

use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cuando cambia el jefe de alguien (por un cambio de jefatura de su unidad,
 * por moverlo de unidad o por una baja con reemplazo), sus papeletas que aún
 * esperan al jefe (Pendiente Jefe u Observada por jefe) pasan al jefe nuevo:
 * solo el jefe actual las decide. Las ya autorizadas, cerradas, etc. no se
 * tocan (son historial).
 *
 * Si el jefe nuevo es null (jefatura vacante) no se reasigna nada: la papeleta
 * queda como está hasta que haya jefe o venza.
 */
class PapeletasAbiertasService
{
    /** Estados en los que la papeleta espera una decisión del jefe inmediato. */
    private const ESTADOS_DEL_JEFE = [PendienteJefe::class, ObservadaPorJefe::class];

    public function contarPendientesDeJefe(User $jefe): int
    {
        return Papeleta::whereState('estado', self::ESTADOS_DEL_JEFE)
            ->where('jefe_inmediato_id', $jefe->id)
            ->count();
    }

    /**
     * Reasigna las papeletas abiertas de $trabajador a su jefe inmediato y de
     * área actuales (columnas ya recalculadas de users).
     *
     * @return int cantidad de papeletas reasignadas
     */
    public function reasignarDeTrabajador(User $trabajador): int
    {
        $jefeId = $trabajador->jefe_inmediato_id;

        if ($jefeId === null) {
            return 0;
        }

        $papeletas = Papeleta::whereState('estado', self::ESTADOS_DEL_JEFE)
            ->where('trabajador_id', $trabajador->id)
            ->where(fn ($q) => $q
                ->where('jefe_inmediato_id', '!=', $jefeId)
                ->orWhereNull('jefe_inmediato_id'))
            ->get();

        foreach ($papeletas as $papeleta) {
            DB::transaction(function () use ($papeleta, $trabajador, $jefeId) {
                $papeleta->forceFill([
                    'jefe_inmediato_id' => $jefeId,
                    'jefe_area_id' => $trabajador->jefe_area_id,
                ])->save();

                HistorialPapeleta::create([
                    'papeleta_id' => $papeleta->id,
                    'actor_id' => null,
                    'actor_tipo' => 'sistema',
                    'estado_nuevo' => class_basename($papeleta->estado),
                    'justificacion' => 'Cambió el jefe inmediato: la papeleta pasa al jefe actual.',
                ]);
            });

            // Fuera de la transacción: un fallo al avisar nunca revierte la reasignación.
            try {
                app(NotificarPapeletaService::class)->creada($papeleta->fresh());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $papeletas->count();
    }
}
