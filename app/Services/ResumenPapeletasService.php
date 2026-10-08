<?php

namespace App\Services;

use App\Models\Papeleta;
use App\Support\Minutos;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use App\States\Papeleta\Finalizada;
use App\States\Papeleta\Vencida;
use Illuminate\Support\Collection;

/**
 * Resumen mensual de papeletas para RRHH: cuántas hubo por motivo y por
 * unidad orgánica, en qué terminaron, y cuánto tardan jefes y RRHH en
 * decidir. El mes es el de `dia_operativo`, igual que el reporte de horas.
 */
class ResumenPapeletasService
{
    /** Agrupación de estados en las cuatro columnas del reporte. */
    private const GRUPOS = [
        'autorizadas' => [
            AutorizadaYCorriendo::class, Cerrada::class, EnJustificacion::class, Finalizada::class,
        ],
        'rechazadas' => [Rechazada::class],
        'no_prosperaron' => [Vencida::class, Cancelada::class],
        'en_tramite' => [PendienteJefe::class, ObservadaPorJefe::class, PendienteRrhh::class, ObservadaPorRrhh::class],
    ];

    public function __construct(private ReporteHorasAcumuladasService $reporte) {}

    /**
     * @return array{por_motivo: Collection, por_unidad: Collection, total: int, minutos_rrhh: ?int, minutos_jefe: ?int, jefes_lentos: Collection}
     */
    public function resumen(string $mes): array
    {
        [$inicio, $fin] = $this->reporte->rangoDelMes($mes);

        $papeletas = Papeleta::query()
            ->with(['motivo:id,nombre', 'trabajador:id,unidad_organica_id', 'trabajador.unidadOrganica:id,nombre', 'resueltoPorJefe:id,name,apellido'])
            ->whereBetween('dia_operativo', [$inicio->toDateString(), $fin->toDateString()])
            ->get();

        return [
            'por_motivo' => $this->agrupar($papeletas, fn (Papeleta $p) => $p->motivo->nombre),
            'por_unidad' => $this->agrupar($papeletas, fn (Papeleta $p) => $p->trabajador?->unidadOrganica?->nombre ?? 'Sin unidad'),
            'total' => $papeletas->count(),
            'minutos_rrhh' => $this->promedio($papeletas, 'jefe_resuelto_at', 'rrhh_resuelto_at'),
            'minutos_jefe' => $this->promedio($papeletas, 'created_at', 'jefe_resuelto_at'),
            'jefes_lentos' => $this->jefesMasLentos($papeletas),
        ];
    }

    /** @param  Collection<int, Papeleta>  $papeletas */
    private function agrupar(Collection $papeletas, callable $clave): Collection
    {
        return $papeletas
            ->groupBy($clave)
            ->map(function (Collection $filas, string $nombre) {
                $fila = ['nombre' => $nombre, 'total' => $filas->count()];

                foreach (self::GRUPOS as $grupo => $estados) {
                    $fila[$grupo] = $filas->filter(fn (Papeleta $p) => in_array(get_class($p->estado), $estados, true))->count();
                }

                return $fila;
            })
            ->sortByDesc('total')
            ->values();
    }

    /** Promedio en minutos entre dos fechas de la papeleta, solo donde ambas existen. */
    private function promedio(Collection $papeletas, string $desde, string $hasta): ?int
    {
        $minutos = $papeletas
            ->filter(fn (Papeleta $p) => $p->{$desde} && $p->{$hasta})
            ->map(fn (Papeleta $p) => Minutos::entre($p->{$desde}, $p->{$hasta}));

        return $minutos->isEmpty() ? null : (int) round($minutos->avg());
    }

    /** @return Collection<int, array{nombre: string, papeletas: int, minutos: int}> */
    private function jefesMasLentos(Collection $papeletas): Collection
    {
        return $papeletas
            ->filter(fn (Papeleta $p) => $p->resueltoPorJefe && $p->jefe_resuelto_at)
            ->groupBy('resuelto_por_jefe_id')
            ->map(fn (Collection $filas) => [
                'nombre' => $filas->first()->resueltoPorJefe->nombre_completo,
                'papeletas' => $filas->count(),
                'minutos' => (int) round($filas->avg(fn (Papeleta $p) => Minutos::entre($p->created_at, $p->jefe_resuelto_at))),
            ])
            ->sortByDesc('minutos')
            ->take(10)
            ->values();
    }
}
