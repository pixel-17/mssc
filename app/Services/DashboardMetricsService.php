<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Models\Retorno;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
 * Caché por versión: cada cambio de papeleta (PapeletaActualizada)
 * incrementa la versión, así nunca se muestra un dashboard viejo tras un
 * evento, pero N dashboards abiertos que se re-renderizan a la vez
 * comparten un solo cálculo (~33 queries) en vez de hacer uno cada uno.
 */
class DashboardMetricsService
{
    /** Ventana usada para las métricas "del último periodo" (conteos, promedios, tasas). */
    protected int $diasVentana = 30;

    public function __construct(
        private readonly ReporteHorasAcumuladasService $reporteHorasAcumuladas,
    ) {}

    public function paraAdmin(): array
    {
        return $this->recordar('admin', fn () => $this->calcularAdmin());
    }

    public function paraRrhh(User $rrhh): array
    {
        // El agregado no depende de CUÁL usuario RRHH lo pide (ver
        // ReporteHorasAcumuladasService::query: cualquier rrhh ve todo
        // el mismo universo), así que la clave de caché sigue siendo
        // "rrhh" sin el id, compartida entre todos los de ese rol.
        return $this->recordar('rrhh', fn () => $this->calcularRrhh($rrhh));
    }

    public function paraJefe(User $jefe): array
    {
        return $this->recordar('jefe:'.$jefe->id, fn () => $this->calcularJefe($jefe));
    }

    /**
     * Invalida todas las métricas. La llama PapeletaActualizada tras el
     * commit, ANTES de emitir el evento: así los dashboards abiertos que
     * se re-renderizan por el aviso leen datos frescos y el resto de
     * renders (33 queries c/u) sale de caché.
     */
    public static function invalidar(): void
    {
        Cache::add(self::CLAVE_VERSION, 1);
        Cache::increment(self::CLAVE_VERSION);
    }

    private const CLAVE_VERSION = 'dashboard-metricas:version';

    /** TTL corto de respaldo por si algún cambio no pasa por PapeletaActualizada (turnos, usuarios). */
    private const TTL_SEGUNDOS = 30;

    private function recordar(string $clave, \Closure $calcular): array
    {
        $version = (int) Cache::get(self::CLAVE_VERSION, 1);

        return Cache::remember("dashboard-metricas:{$version}:{$clave}", self::TTL_SEGUNDOS, $calcular);
    }

    private function calcularAdmin(): array
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
            'papeletas_activas' => Papeleta::whereNotState('estado', $this->estadosTerminales())->count(),
            'papeletas_periodo' => Papeleta::where('created_at', '>=', $desde)->count(),
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

    private function calcularRrhh(User $rrhh): array
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

        // "Quién está afuera ahora mismo": lista en vivo (se re-renderiza
        // con el resto del dashboard vía EscuchaNotificacionesEnVivo), la
        // más antigua primero porque es la que más atención necesita.
        // Se recorta a 8 para no volver pesado un widget de resumen; el
        // total real va aparte para poder avisar "+N más".
        $afueraQuery = Papeleta::whereState('estado', AutorizadaYCorriendo::class);
        $trabajadoresAfueraTotal = (clone $afueraQuery)->count();
        $trabajadoresAfuera = $afueraQuery
            ->with(['trabajador', 'motivo'])
            ->orderBy('hora_salida_real')
            ->limit(8)
            ->get();

        $mesActual = now()->format('Y-m');
        $horasAcumuladasTop = $this->reporteHorasAcumuladas
            ->resumenPorTrabajador($rrhh, $mesActual)
            ->take(5);

        return [
            'pendientes_decision' => Papeleta::whereState('estado', PendienteRrhh::class)->count(),
            'posthoc_pendientes' => Papeleta::where('autorizado_con_rrhh_fuera_horario', true)
                ->where('revision_posthoc_estado', 'pendiente')
                ->count(),
            'sustentos_por_revisar' => Papeleta::whereState('estado', RetornoPendienteSustento::class)
                ->whereHas('sustentos', fn ($q) => $q->where('estado', 'presentado'))
                ->count(),
            'promedio_resolucion_minutos' => $promedioMinutos,
            'papeletas_por_motivo' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->join('motivos', 'motivos.id', '=', 'papeletas.motivo_id')
                ->selectRaw('motivos.nombre as etiqueta, count(*) as total')
                ->groupBy('motivos.nombre')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'trabajadores_afuera' => $trabajadoresAfuera,
            'trabajadores_afuera_total' => $trabajadoresAfueraTotal,
            // Top 5 trabajadores con más papeletas creadas en el periodo.
            'papeletas_por_trabajador' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->join('users as trabajadores', 'trabajadores.id', '=', 'papeletas.trabajador_id')
                ->selectRaw("CONCAT(trabajadores.name, ' ', trabajadores.apellido) as etiqueta, count(*) as total")
                ->groupBy('trabajadores.id', 'trabajadores.name', 'trabajadores.apellido')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'horas_acumuladas_mes' => $mesActual,
            'horas_acumuladas_top' => $horasAcumuladasTop,
            // Seguimiento de calidad, no un "ranking": papeletas rechazadas
            // u observadas por RRHH en el periodo, agrupadas por trabajador.
            // Mismo criterio de "atención requerida" que en las bandejas,
            // no una lista pensada para exponer públicamente.
            'seguimiento_por_trabajador' => Papeleta::where('papeletas.created_at', '>=', $desde)
                ->where(function ($q) {
                    $q->whereState('estado', Rechazada::class)
                        ->orWhere('contador_observaciones_rrhh', '>=', 1);
                })
                ->join('users as trabajadores', 'trabajadores.id', '=', 'papeletas.trabajador_id')
                ->selectRaw("CONCAT(trabajadores.name, ' ', trabajadores.apellido) as etiqueta, count(*) as total")
                ->groupBy('trabajadores.id', 'trabajadores.name', 'trabajadores.apellido')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ];
    }

    private function calcularJefe(User $jefe): array
    {
        $desde = now()->subDays($this->diasVentana);

        $baseEquipo = fn () => Papeleta::deEquipoDe($jefe);

        $decididasPeriodo = $baseEquipo()
            ->where('created_at', '>=', $desde)
            ->whereState('estado', [Cerrada::class, Rechazada::class, AutorizadaYCorriendo::class, Vencida::class])
            ->get(['estado']);

        $tasaAprobacion = $decididasPeriodo->isEmpty()
            ? null
            : (int) round(
                $decididasPeriodo->filter(fn ($p) => ! ($p->estado instanceof Rechazada))->count()
                    / $decididasPeriodo->count() * 100
            );

        return [
            'equipo_total' => User::equipoDe($jefe)->count(),
            'por_decidir' => $baseEquipo()->whereState('estado', PendienteJefe::class)->count(),
            'observaciones_rrhh' => Papeleta::whereState('estado', ObservadaPorRrhh::class)
                ->deJefeInmediato($jefe)
                ->count(),
            'en_curso' => Papeleta::whereState('estado', AutorizadaYCorriendo::class)
                ->deJefeInmediato($jefe)
                ->count(),
            'sustentos_por_revisar' => Papeleta::whereState('estado', RetornoPendienteSustento::class)
                ->deJefeInmediato($jefe)
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
