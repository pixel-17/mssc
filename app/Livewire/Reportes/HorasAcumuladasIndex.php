<?php

namespace App\Livewire\Reportes;

use App\Exports\HorasAcumuladasExport;
use App\Models\Motivo;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\ReporteHorasAcumuladasService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
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
class HorasAcumuladasIndex extends Component
{
    public string $mes;

    public ?int $trabajadorId = null;

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    public ?int $motivoId = null;

    public ?string $regimen = null;

    public bool $soloConDescuento = false;

    public string $vista = 'resumen';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole('admin') && ! $user->hasRole('rrhh') && ! $user->can('crearTrabajadorPropio', User::class)) {
            $this->redirectRoute('trabajador.papeletas.index');
        }

        $this->mes = now()->format('Y-m');
    }

    protected function filtros(): array
    {
        return [
            'trabajador_id' => $this->trabajadorId,
            'sede_id' => $this->sedeId,
            'unidad_organica_id' => $this->unidadOrganicaId,
            'motivo_id' => $this->motivoId,
            'regimen' => $this->regimen,
            'solo_con_descuento' => $this->soloConDescuento,
        ];
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['trabajadorId', 'sedeId', 'unidadOrganicaId', 'motivoId', 'regimen', 'soloConDescuento']);
    }

    /**
     * Descarga en Excel (2 hojas: resumen mensual + detalle diario)
     * respetando el mismo mes y filtros que se están viendo en pantalla.
     */
    public function exportar(ReporteHorasAcumuladasService $service)
    {
        /** @var User $user */
        $user = Auth::user();

        $resumen = $service->resumenPorTrabajador($user, $this->mes, $this->filtros());
        $detalle = $service->detalle($user, $this->mes, $this->filtros());

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
            : collect($user->trabajadoresComoJefeInmediato())->sortBy('name')->values();

        return view('livewire.reportes.horas-acumuladas-index', [
            'resumen' => $service->resumenPorTrabajador($user, $this->mes, $this->filtros()),
            'detalleDiario' => $service->resumenDiarioPorTrabajador($user, $this->mes, $this->filtros()),
            'trabajadoresDisponibles' => $trabajadoresDisponibles,
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(),
            'unidadesOrganicas' => UnidadOrganica::where('activo', true)->orderBy('nombre')->get(),
            'motivos' => Motivo::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
