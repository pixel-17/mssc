<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ficha completa de UN trabajador: buscador (para elegir a quién ver)
 * + su historial íntegro de papeletas, sin recortar por mes a
 * diferencia de ReporteHorasAcumuladasService — pensado para revisar
 * un caso puntual a fondo ("¿qué ha hecho fulano en todo el sistema?"),
 * no para el cierre mensual de planilla.
 */
class HistorialTrabajadorService
{
    /**
     * Trabajadores que $usuario puede buscar: admin/RRHH a cualquiera,
     * jefe solo a quienes ya ve en su propio equipo (automáticos +
     * adicionales, ver User::trabajadoresParaReportes()).
     */
    public function trabajadoresVisibles(User $usuario, string $buscar = ''): Collection
    {
        if ($usuario->hasRole('admin') || $usuario->hasRole('rrhh')) {
            return User::role('trabajador')
                ->when($buscar !== '', fn (Builder $q) => $q->where(function (Builder $q2) use ($buscar) {
                    $q2->where('name', 'like', "%{$buscar}%")
                        ->orWhere('apellido', 'like', "%{$buscar}%")
                        ->orWhere('dni', 'like', "%{$buscar}%");
                }))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name', 'apellido', 'dni']);
        }

        $equipo = $usuario->trabajadoresParaReportes();

        if ($buscar === '') {
            return $equipo->sortBy('name')->take(15)->values();
        }

        $buscarNormalizado = mb_strtolower($buscar);

        return $equipo
            ->filter(fn (User $t) => str_contains(mb_strtolower($t->nombre_completo.' '.$t->dni), $buscarNormalizado))
            ->sortBy('name')
            ->take(15)
            ->values();
    }

    /**
     * ¿Puede $usuario ver la ficha de $trabajador? Admin/RRHH siempre;
     * jefe solo si es su jefe inmediato (automático o adicional) o su
     * jefe de área explícito.
     */
    public function puedeVer(User $usuario, User $trabajador): bool
    {
        if ($usuario->hasRole('admin') || $usuario->hasRole('rrhh')) {
            return true;
        }

        return User::equipoDe($usuario)->whereKey($trabajador->id)->exists();
    }

    /**
     * Todo el historial de papeletas del trabajador (cualquier estado,
     * cualquier fecha), más reciente primero.
     */
    public function historial(User $trabajador): Collection
    {
        return Papeleta::where('trabajador_id', $trabajador->id)
            ->with(['motivo', 'sede', 'retorno', 'sustentos'])
            ->orderByDesc('dia_operativo')
            ->get()
            ->map(fn (Papeleta $p) => [
                'id' => $p->id,
                'dia' => $p->dia_operativo,
                'motivo' => $p->motivo->nombre,
                'sede' => $p->sede?->nombre,
                'estado' => class_basename($p->estado),
                // FQCN completo para <x-estado-papeleta>: evita que la vista
                // tenga que rearmar 'App\States\Papeleta\'.$fila['estado'],
                // que se rompe en silencio si cambia el formato de arriba.
                'estado_fqcn' => get_class($p->estado),
                'es_emergencia' => $p->es_emergencia,
                'salida' => $p->hora_salida_real,
                'retorno' => $p->retorno?->hora_servidor,
                'minutos' => ($p->hora_salida_real && $p->retorno)
                    ? $p->hora_salida_real->diffInMinutes($p->retorno->hora_servidor)
                    : null,
                'suma_descuento' => (bool) $p->motivo->suma_descuento,
                'sustento_estado' => $p->sustentos->last()?->estado,
            ]);
    }

    /**
     * Totales de siempre a partir del historial ya cargado: papeletas
     * por condición, horas acumuladas (solo las ya cerradas con
     * retorno) y horas que sí cuentan para descuento.
     *
     * @param  Collection<int, array<string, mixed>>  $historial
     * @return array<string, int>
     */
    public function resumen(Collection $historial): array
    {
        $conHoras = $historial->filter(fn (array $f) => $f['minutos'] !== null);

        return [
            'papeletas_total' => $historial->count(),
            'rechazadas' => $historial->where('estado', 'Rechazada')->count(),
            'vencidas' => $historial->where('estado', 'Vencida')->count(),
            'emergencias' => $historial->where('es_emergencia', true)->count(),
            'minutos_totales' => $conHoras->sum('minutos'),
            'minutos_con_descuento' => $conHoras->where('suma_descuento', true)->sum('minutos'),
        ];
    }
}
