<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\Retorno;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\FinalizadoSinRetorno;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\ReclasificadoAParticular;
use App\States\Papeleta\Rechazada;
use App\States\Papeleta\RetornoPendienteSustento;
use App\States\Papeleta\Vencida;

/**
 * Métricas reales para el dashboard nuevo (admin, RRHH, jefe). Cada
 * método arma SOLO lo que ese rol puede ver — misma frontera de
 * visibilidad que ya aplican los Controllers de bandeja (Jefe/Rrhh),
 * para no filtrar desde acá datos que ese rol no debería ver.
 *
 * Deliberadamente no cachea nada: las consultas van sobre 'estado'
 * (columna indexada, ver migración de papeletas) y son pocas, así que
 * el costo de recalcular en cada visita es bajo y evita mostrar un
 * dashboard desactualizado justo cuando más importa (jefe/RRHH
 * decidiendo en el momento).
 */
class DashboardMetricsService
{
    /** Ventana usada para las métricas "del último periodo" (conteos, promedios, tasas). */
    protected int $diasVentana = 30;

    public function paraAdmin(): array
    {
        $desde = now()->subDays($this->diasVentana);

        return [
            'usuarios_total' => User::count(),
            'usuarios_por_rol' => [
                'admin' => User::role('admin')->count(),
                'rrhh' => User::role('rrhh')->count(),
                'trabajador' => User::role('trabajador')->count(),
            ],
            'sedes_activas' => Sede::where('activo', true)->count(),
            'unidades_organicas' => UnidadOrganica::where('activo', true)->count(),
            'papeletas_activas' => Papeleta::whereNotIn('estado', $this->estadosTerminales())->count(),
            'papeletas_periodo' => Papeleta::where('created_at', '>=', $desde)->count(),
            'emergencias_activas' => Papeleta::where('es_emergencia', true)
                ->whereNotIn('estado', $this->estadosTerminales())
                ->count(),
            'retornos_manuales_periodo' => Retorno::where('marcado_manual', true)
                ->where('created_at', '>=', $desde)
                ->count(),
            'papeletas_por_sede' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->join('sedes', 'sedes.id', '=', 'papeletas.sede_id')
                ->selectRaw('sedes.nombre as etiqueta, count(*) as total')
                ->groupBy('sedes.nombre')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ];
    }

    public function paraRrhh(): array
    {
        $desde = now()->subDays($this->diasVentana);

        $resueltasPeriodo = Papeleta::whereNotNull('rrhh_resuelto_at')
            ->where('rrhh_resuelto_at', '>=', $desde)
            ->get(['created_at', 'rrhh_resuelto_at']);

        $promedioMinutos = $resueltasPeriodo->isEmpty()
            ? null
            : (int) round($resueltasPeriodo->avg(
                fn (Papeleta $p) => $p->created_at->diffInMinutes($p->rrhh_resuelto_at)
            ));

        return [
            'pendientes_decision' => Papeleta::where('estado', PendienteRrhh::class)->count(),
            'posthoc_pendientes' => Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
                ->where('revision_posthoc_estado', 'pendiente')
                ->count(),
            'sustentos_por_revisar' => Papeleta::where('estado', RetornoPendienteSustento::class)
                ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
                ->count(),
            'emergencias_activas' => Papeleta::where('es_emergencia', true)
                ->whereNotIn('estado', $this->estadosTerminales())
                ->count(),
            'promedio_resolucion_minutos' => $promedioMinutos,
            'papeletas_por_motivo' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->join('motivos', 'motivos.id', '=', 'papeletas.motivo_id')
                ->selectRaw('motivos.nombre as etiqueta, count(*) as total')
                ->groupBy('motivos.nombre')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ];
    }

    public function paraJefe(User $jefe): array
    {
        $desde = now()->subDays($this->diasVentana);

        $baseEquipo = fn () => Papeleta::where(function ($q) use ($jefe) {
            $q->where('jefe_inmediato_id', $jefe->id)->orWhere('jefe_area_id', $jefe->id);
        });

        $decididasPeriodo = $baseEquipo()
            ->where('created_at', '>=', $desde)
            ->whereIn('estado', [Cerrada::class, Rechazada::class, AutorizadaYCorriendo::class, Vencida::class])
            ->get(['estado']);

        $tasaAprobacion = $decididasPeriodo->isEmpty()
            ? null
            : (int) round(
                $decididasPeriodo->filter(fn ($p) => ! ($p->estado instanceof Rechazada))->count()
                    / $decididasPeriodo->count() * 100
            );

        return [
            'equipo_total' => $jefe->trabajadoresComoJefeInmediato()->count(),
            'por_decidir' => $baseEquipo()->where('estado', PendienteJefe::class)->count(),
            'observaciones_rrhh' => Papeleta::where('estado', ObservadaPorRrhh::class)
                ->where('jefe_inmediato_id', $jefe->id)
                ->count(),
            'en_curso' => Papeleta::where('estado', AutorizadaYCorriendo::class)
                ->where('jefe_inmediato_id', $jefe->id)
                ->count(),
            'sustentos_por_revisar' => Papeleta::where('estado', RetornoPendienteSustento::class)
                ->where('jefe_inmediato_id', $jefe->id)
                ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
                ->count(),
            'tasa_aprobacion_periodo' => $tasaAprobacion,
        ];
    }

    /**
     * Estados terminales de Papeleta (ver PapeletaState::esTerminal() en
     * cada clase de app/States/Papeleta) — se repiten aquí como lista
     * porque esTerminal() es un método de instancia, no estático, y no
     * vale la pena instanciar cada estado solo para preguntarle esto.
     *
     * @return array<class-string>
     */
    protected function estadosTerminales(): array
    {
        return [
            Cerrada::class,
            Rechazada::class,
            Vencida::class,
            Cancelada::class,
            FinalizadoSinRetorno::class,
            ReclasificadoAParticular::class,
        ];
    }
}
