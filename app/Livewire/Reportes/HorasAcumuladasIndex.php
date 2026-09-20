<?php

namespace App\Livewire\Reportes;

use App\Exports\HorasAcumuladasExport;
use App\Livewire\Concerns\RestringeAReportes;
use App\Models\Motivo;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\ReporteHorasAcumuladasService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporte administrativo-empresarial: a fin de mes, saber qué
 * trabajador acumuló más horas fuera de sede y a quién le corresponde
 * descuento. Distinto del dashboard de métricas (App\Livewire\DashboardIndex):
 * ese es "en vivo" para operar el flujo; este es "de cierre" para
 * revisar planilla, con rango por mes, filtros libres y exportación.
 *
 * Mismo criterio de acceso que el dashboard: admin/RRHH ven todo el
 * personal, jefe (inmediato o de área, no es rol Spatie, ver
 * UserPolicy::crearTrabajadorPropio) solo ve su propio equipo — la
 * frontera real la aplica ReporteHorasAcumuladasService::query().
 */
#[Layout('layouts.app')]
#[Title('Horas acumuladas')]
class HorasAcumuladasIndex extends Component
{
    use RestringeAReportes;

    public string $mes;

    public ?int $trabajadorId = null;

    /** Búsqueda libre por nombre, apellido o DNI. */
    public string $buscar = '';

    /** Cuántos mostrar en el ranking: 0 = todos (solo afecta la pantalla, no el Excel). */
    public int $top = 0;

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    public ?int $motivoId = null;

    public ?string $regimen = null;

    public bool $soloConDescuento = false;

    public string $vista = 'resumen';

    public function mount(): void
    {
        $this->autorizarAccesoAReportes();

        $this->mes = now()->format('Y-m');
    }

    protected function filtros(): array
    {
        return [
            'trabajador_id' => $this->trabajadorId,
            'buscar' => $this->buscar,
            'sede_id' => $this->sedeId,
            'unidad_organica_id' => $this->unidadOrganicaId,
            'motivo_id' => $this->motivoId,
            'regimen' => $this->regimen,
            'solo_con_descuento' => $this->soloConDescuento,
        ];
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['trabajadorId', 'buscar', 'top', 'sedeId', 'unidadOrganicaId', 'motivoId', 'regimen', 'soloConDescuento']);
    }

    /**
     * Descarga en Excel (2 hojas: resumen mensual + detalle diario)
     * respetando el mismo mes y filtros que se están viendo en pantalla.
     */
    public function exportar(ReporteHorasAcumuladasService $service)
    {
        /** @var User $user */
        $user = Auth::user();

        $detalle = $service->detalle($user, $this->mes, $this->filtros());
        $resumen = $service->resumenPorTrabajador($user, $this->mes, $this->filtros(), $detalle);

        $nombreArchivo = "horas-acumuladas-{$this->mes}.xlsx";

        return Excel::download(new HorasAcumuladasExport($resumen, $detalle), $nombreArchivo);
    }

    public function render(ReporteHorasAcumuladasService $service): View
    {
        /** @var User $user */
        $user = Auth::user();

        $esAdmin = $user->hasRole('admin');
        $esRrhh = $user->hasRole('rrhh');

        $trabajadoresDisponibles = ($esAdmin || $esRrhh)
            ? User::role('trabajador')->orderBy('name')->get(['id', 'name', 'apellido'])
            : $user->trabajadoresParaReportes()->sortBy('name')->values();

        // Admin/RRHH filtran contra toda la organización (catálogo
        // completo). Un jefe solo debería poder filtrar por sedes y
        // unidades que existen dentro de su propio equipo — mostrarle
        // el catálogo completo filtraba datos de estructura
        // organizacional (nombres de sedes/unidades) fuera de su
        // alcance, aunque la query de papeletas ya estaba protegida.
        $sedesQuery = Sede::where('activo', true);
        $unidadesQuery = UnidadOrganica::where('activo', true);

        if (! $esAdmin && ! $esRrhh) {
            $sedeIds = $trabajadoresDisponibles->pluck('sede_id')->filter()->unique();
            $unidadIds = $trabajadoresDisponibles->pluck('unidad_organica_id')->filter()->unique();

            $sedesQuery->whereIn('id', $sedeIds);
            $unidadesQuery->whereIn('id', $unidadIds);
        }

        $detalle = $service->detalle($user, $this->mes, $this->filtros());

        return view('livewire.reportes.horas-acumuladas-index', [
            'resumen' => $service->resumenPorTrabajador($user, $this->mes, $this->filtros(), $detalle),
            'detalleDiario' => $service->resumenDiarioPorTrabajador($user, $this->mes, $this->filtros(), $detalle),
            'trabajadoresDisponibles' => $trabajadoresDisponibles,
            'sedes' => $sedesQuery->orderBy('nombre')->get(),
            'unidadesOrganicas' => $unidadesQuery->orderBy('nombre')->get(),
            'motivos' => Motivo::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
