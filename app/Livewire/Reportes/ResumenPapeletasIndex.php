<?php

namespace App\Livewire\Reportes;

use App\Exports\HorasAcumuladasExport;
use App\Livewire\Concerns\RestringeAReportes;
use App\Models\Motivo;
use App\Models\Sede;
use App\Models\User;
use App\Services\ReporteHorasAcumuladasService;
use App\Services\ResumenPapeletasService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporte de cierre de mes para RR. HH. (y jefes, parcialmente), en dos
 * pestañas sobre el mismo mes:
 *
 *  - "resumen" (antes ResumenPapeletasIndex): conteos por motivo/unidad
 *    y tiempos de respuesta — "salud del proceso". Solo RR. HH./admin,
 *    igual que antes (Decisiones de RR. HH. se evalúa aparte, no se
 *    toca acá).
 *  - "horas" (antes HorasAcumuladasIndex): horas fuera de sede por
 *    trabajador, para el descuento de planilla, con filtros propios y
 *    exportación a Excel. Mismo alcance que antes: admin/RR. HH. ven
 *    todo, jefe solo su equipo (ReporteHorasAcumuladasService::query()).
 *
 * Antes eran dos pantallas (y una ruta exclusiva de jefe); se fusionan
 * porque ambas sirven la misma tarea de fin de mes, y de paso Resumen
 * de papeletas hereda el export que le faltaba. Cada pestaña calcula
 * SOLO sus propios datos (nada de correr las dos consultas pesadas en
 * cada render) y los catálogos de filtros de la pestaña "horas" están
 * memoizados con #[Computed] para no repetirlos en cada wire:model.live.
 */
#[Layout('layouts.app')]
#[Title('Resumen de papeletas')]
class ResumenPapeletasIndex extends Component
{
    use RestringeAReportes;

    /** Pestaña activa: 'resumen' | 'horas'. Deep-link desde el dashboard. */
    #[Url(as: 'tab', keep: false)]
    public string $tab = '';

    public string $mes;

    // --- Filtros exclusivos de la pestaña "horas" ---
    public ?int $trabajadorId = null;

    public string $buscar = '';

    /** Cuántos mostrar en el ranking: 0 = todos (solo afecta la pantalla, no el Excel). */
    public int $top = 0;

    public ?int $sedeId = null;

    public ?int $unidadOrganicaId = null;

    public ?int $motivoId = null;

    public ?string $regimen = null;

    public bool $soloConDescuento = false;

    /** Sub-vista dentro de la pestaña "horas": 'resumen' | 'diario'. */
    public string $vista = 'resumen';

    public function mount(): void
    {
        $this->autorizarAccesoAReportes();

        $this->mes = now()->format('Y-m');

        // La pestaña "resumen" fue eliminada de la vista; siempre se aterriza
        // en "horas" independientemente del rol.
        if ($this->tab === '' || $this->tab === 'resumen') {
            $this->tab = 'horas';
        }

        $this->normalizarTab();
    }

    public function cambiarTab(string $tab): void
    {
        $this->tab = $tab;

        $this->normalizarTab();
    }

    /** Un jefe no tiene la pestaña "resumen": si llega ahí (URL a mano), se cae a "horas". */
    private function normalizarTab(): void
    {
        if ($this->tab === 'resumen' && ! $this->puedeVerResumen()) {
            $this->tab = 'horas';
        }
    }

    private function puedeVerResumen(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->hasRole('admin') || $user->hasRole('rrhh');
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
     * Descarga en Excel (2 hojas: resumen mensual + detalle diario) de la
     * pestaña "horas", respetando el mismo mes y filtros que se están
     * viendo en pantalla.
     */
    public function exportar(ReporteHorasAcumuladasService $service)
    {
        $this->validate([
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $detalle = $service->detalle($user, $this->mes, $this->filtros());
        $resumen = $service->resumenPorTrabajador($user, $this->mes, $this->filtros(), $detalle);

        $nombreArchivo = "horas-acumuladas-{$this->mes}.xlsx";

        return Excel::download(new HorasAcumuladasExport($resumen, $detalle), $nombreArchivo);
    }

    /** Trabajadores disponibles para el filtro de la pestaña "horas" (memoizado: una vez por request). */
    #[Computed]
    public function trabajadoresDisponibles(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->puedeVerResumen()
            ? User::role('trabajador')->orderBy('name')->get(['id', 'name', 'apellido'])
            : $user->trabajadoresParaReportes()->sortBy('name')->values();
    }

    /**
     * Admin/RRHH filtran contra toda la organización (catálogo completo).
     * Un jefe solo debería poder filtrar por sedes y unidades que existen
     * dentro de su propio equipo — mostrarle el catálogo completo filtraba
     * datos de estructura organizacional fuera de su alcance, aunque la
     * query de papeletas ya estaba protegida.
     */
    #[Computed]
    public function sedes(): Collection
    {
        $query = Sede::where('activo', true);

        if (! $this->puedeVerResumen()) {
            $query->whereIn('id', $this->trabajadoresDisponibles->pluck('sede_id')->filter()->unique());
        }

        return $query->orderBy('nombre')->get();
    }

    #[Computed]
    public function motivos(): Collection
    {
        return Motivo::where('activo', true)->orderBy('nombre')->get();
    }

    public function render(ReporteHorasAcumuladasService $horasService, ResumenPapeletasService $resumenService): View
    {
        $resumenPapeletas = null;
        $resumenHoras = null;
        $detalleDiario = null;

        if ($this->tab === 'resumen') {
            $resumenPapeletas = $resumenService->resumen($this->mes);
        } else {
            /** @var User $user */
            $user = Auth::user();

            $detalle = $horasService->detalle($user, $this->mes, $this->filtros());
            $resumenHoras = $horasService->resumenPorTrabajador($user, $this->mes, $this->filtros(), $detalle);
            $detalleDiario = $horasService->resumenDiarioPorTrabajador($user, $this->mes, $this->filtros(), $detalle);
        }

        return view('livewire.reportes.resumen-papeletas-index', [
            'puedeVerResumen' => $this->puedeVerResumen(),
            'resumenPapeletas' => $resumenPapeletas,
            'resumen' => $resumenHoras,
            'detalleDiario' => $detalleDiario,
            'sedes' => $this->sedes,
            'motivos' => $this->motivos,
        ]);
    }
}
