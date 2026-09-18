<?php

namespace App\Services;

use App\Models\Sustento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reporte de sustentos (Paso 8, motivo Salud): qué archivos están
 * subiendo los trabajadores para justificar su retorno, en qué estado
 * está cada uno (pendiente de subir / presentado, por revisar /
 * aprobado) y quién lo revisó. Misma frontera de visibilidad que el
 * resto de reportes: admin/RRHH ven todo, jefe solo su equipo.
 */
class ReporteSustentosService
{
    /**
     * @param  array{trabajador_id?: int|null, buscar?: string|null, estado?: string|null, desde?: string|null, hasta?: string|null}  $filtros
     */
    public function query(User $usuario, array $filtros = []): Builder
    {
        $query = Sustento::query()
            ->with(['papeleta.trabajador.sede', 'papeleta.motivo', 'revisadoPor']);

        if (! $usuario->hasRole('admin') && ! $usuario->hasRole('rrhh')) {
            $query->whereHas('papeleta', fn (Builder $q) => $q->where(function (Builder $q2) use ($usuario) {
                $q2->where('jefe_inmediato_id', $usuario->id)
                    ->orWhere('jefe_area_id', $usuario->id);
            }));
        }

        if (! empty($filtros['trabajador_id'])) {
            $query->whereHas('papeleta', fn (Builder $q) => $q->where('trabajador_id', $filtros['trabajador_id']));
        }

        // Búsqueda libre por nombre, apellido o DNI del trabajador.
        if (! empty($filtros['buscar'])) {
            $buscar = trim($filtros['buscar']);

            $query->whereHas('papeleta.trabajador', fn (Builder $q) => $q->where(function (Builder $q2) use ($buscar) {
                $q2->where('name', 'like', "%{$buscar}%")
                    ->orWhere('apellido', 'like', "%{$buscar}%")
                    ->orWhere('dni', 'like', "%{$buscar}%");
            }));
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('created_at', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['hasta']);
        }

        return $query->orderByDesc('created_at');
    }
}
