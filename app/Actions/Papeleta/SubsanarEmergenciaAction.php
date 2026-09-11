<?php

namespace App\Actions\Papeleta;

use App\Exceptions\PapeletaException;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\ReclasificadoAParticular;
use Illuminate\Support\Facades\DB;

/**
 * Paso 6: subir la subsanación NO aprueba nada por sí sola (misma
 * regla de legitimidad de adjuntos que Paso 5/Paso 8) — solo reabre a
 * 'pendiente' el/los visto bueno que quedaron en 'observado', para que
 * ese mismo revisor vuelva a decidir antes de
 * subsanacion_emergencia_fecha_limite. Si el plazo ya venció (el job
 * ya reclasificó a Particular), ya no hay nada que subsanar.
 */
class SubsanarEmergenciaAction
{
    public function __construct(private NotificarPapeletaService $notificar) {}

    public function ejecutar(Papeleta $papeleta, User $trabajador, string $justificacion, ?string $adjuntoPath = null): Papeleta
    {
        if ($papeleta->trabajador_id !== $trabajador->id) {
            throw new PapeletaException('No puedes subsanar una papeleta que no es tuya.');
        }

        if ($papeleta->estado->equals(ReclasificadoAParticular::class)) {
            throw new PapeletaException('El plazo de subsanación ya venció; esta papeleta fue reclasificada a Particular.');
        }

        $rolesObservados = collect(['jefe', 'rrhh'])
            ->filter(fn (string $rol) => $papeleta->{"visto_bueno_{$rol}_emergencia"} === 'observado');

        if ($rolesObservados->isEmpty()) {
            throw new PapeletaException('Esta Emergencia no tiene ninguna observación pendiente de subsanar.');
        }

        $papeleta = DB::transaction(function () use ($papeleta, $trabajador, $justificacion, $adjuntoPath, $rolesObservados) {
            $estadoAnterior = class_basename($papeleta->estado);

            foreach ($rolesObservados as $rol) {
                $papeleta->{"visto_bueno_{$rol}_emergencia"} = 'pendiente';
            }

            if ($adjuntoPath) {
                $papeleta->subsanacion_emergencia_adjunto_path = $adjuntoPath;
            }

            $papeleta->save();

            HistorialPapeleta::create([
                'papeleta_id' => $papeleta->id,
                'actor_id' => $trabajador->id,
                'actor_tipo' => 'trabajador',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => class_basename($papeleta->estado),
                'justificacion' => $justificacion,
            ]);

            return $papeleta;
        });

        $this->notificar->emergenciaSubsanada($papeleta, $rolesObservados->all());

        return $papeleta;
    }
}
