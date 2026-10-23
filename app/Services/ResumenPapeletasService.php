<?php

namespace App\Services;

use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use App\States\Papeleta\Vencida;
use App\Support\Minutos;
use Carbon\CarbonInterface;
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
        ->select([
            'id',
            'motivo_id',
            'trabajador_id',
            'estado',
            'dia_operativo',
            'created_at',
            'jefe_resuelto_at',
            'rrhh_resuelto_at',
            'resuelto_por_jefe_id',
        ])
        ->with([
            'motivo:id,nombre',
            'trabajador:id,unidad_organica_id',
            'trabajador.unidadOrganica:id,nombre',
            'resueltoPorJefe:id,name,apellido',
        ])
        ->whereBetween('dia_operativo', [
            $inicio->toDateString(),
            $fin->toDateString(),
        ])
        ->get();

    return [
        'por_motivo' => $this->agrupar(
            $papeletas,
            fn (Papeleta $p) => $p->motivo->nombre,
        ),
        'por_unidad' => $this->agrupar(
            $papeletas,
            fn (Papeleta $p) => $p->trabajador?->unidadOrganica?->nombre ?? 'Sin unidad',
        ),
        'total' => $papeletas->count(),
        'minutos_rrhh' => $this->promedio(
            $papeletas,
            fn (Papeleta $p) => $p->jefe_resuelto_at,
            fn (Papeleta $p) => $p->rrhh_resuelto_at,
        ),
        'minutos_jefe' => $this->promedio(
            $papeletas,
            fn (Papeleta $p) => $p->created_at,
            fn (Papeleta $p) => $p->jefe_resuelto_at,
        ),
        'jefes_lentos' => $this->jefesMasLentos($papeletas),
    ];
}

/**
 * @param Collection<int, Papeleta> $papeletas
 * @param callable(Papeleta): string $clave
 * @return Collection<int, array{
 *     nombre: string,
 *     autorizadas: int,
 *     rechazadas: int,
 *     no_prosperaron: int,
 *     en_tramite: int,
 *     total: int
 * }>
 */
private function agrupar(Collection $papeletas, callable $clave): Collection
{
    return $papeletas
        ->groupBy($clave)
        ->map(function (Collection $filas, string $nombre): array {
            $fila = [
                'nombre' => $nombre,
                'autorizadas' => 0,
                'rechazadas' => 0,
                'no_prosperaron' => 0,
                'en_tramite' => 0,
            ];

            foreach (self::GRUPOS as $grupo => $estados) {
                $fila[$grupo] = $filas
                    ->filter(
                        fn (Papeleta $p): bool => in_array(
                            $p->estado::class,
                            $estados,
                            true,
                        ),
                    )
                    ->count();
            }

            $fila['total'] = $filas->count();

            return $fila;
        })
        ->sortByDesc('total')
        ->values();
}


    /** Promedio en minutos entre dos fechas de la papeleta, solo donde ambas existen. */
    
/**
 * @param Collection<int, Papeleta> $papeletas
 * @param callable(Papeleta): (\Carbon\CarbonInterface|null) $obtenerInicio
 * @param callable(Papeleta): (\Carbon\CarbonInterface|null) $obtenerFin
 */
private function promedio(
    Collection $papeletas,
    callable $obtenerInicio,
    callable $obtenerFin,
): ?int {
    $minutos = $papeletas
        ->map(function (Papeleta $p) use ($obtenerInicio, $obtenerFin): ?int {
            $inicio = $obtenerInicio($p);
            $fin = $obtenerFin($p);

            if ($inicio === null || $fin === null) {
                return null;
            }

            return Minutos::entre($inicio, $fin);
        })
        ->filter(fn (?int $minutos): bool => $minutos !== null);

    return $minutos->isEmpty() ? null : (int) round($minutos->avg());
}

    /** @return Collection<int, array{nombre: string, papeletas: int, minutos: int}> */
    /** @return Collection<int, array{nombre: string, papeletas: int, minutos: int}> */
    private function jefesMasLentos(Collection $papeletas): Collection
    {
        return $papeletas
            ->filter(fn (Papeleta $p) => $p->resueltoPorJefe && $p->jefe_resuelto_at)
            ->groupBy('resuelto_por_jefe_id')
            ->map(function (Collection $filas): array {
                /** @var Papeleta $primera */
                $primera = $filas->first();

                $minutos = $filas
                    ->map(function (Papeleta $p): ?int {
                        if (! $p->created_at instanceof CarbonInterface
                            || ! $p->jefe_resuelto_at instanceof CarbonInterface) {
                            return null;
                        }

                        return Minutos::entre($p->created_at, $p->jefe_resuelto_at);
                    })
                    ->filter(fn (?int $minutos): bool => $minutos !== null);

                return [
                    'nombre' => (string) $primera->resueltoPorJefe->nombre_completo,
                    'papeletas' => $filas->count(),
                    'minutos' => $minutos->isEmpty()
                        ? 0
                        : (int) round($minutos->avg()),
                ];
            })
            ->sortByDesc('minutos')
            ->take(10)
            ->values();
    }
}
