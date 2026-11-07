<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel de cierre de mes: hoja "Resumen" (una fila por trabajador, para
 * decidir descuentos) + hoja "Detalle" (una fila por papeleta, para
 * sustentar cada descuento si RRHH o el trabajador lo cuestiona).
 *
 * Recibe las colecciones YA calculadas por ReporteHorasAcumuladasService
 * (mismo mes/filtros que se ven en pantalla) en vez de recalcular acá,
 * para que lo exportado sea exactamente lo que el usuario está viendo.
 */
class HorasAcumuladasExport implements Export, WithMultipleSheets
{
    /**
     * @param  Collection<int, array{trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, papeletas: int, minutos_totales: int, minutos_con_descuento: int, papeletas_con_descuento: int}>  $resumen
     * @param  Collection<int, array{papeleta_id: int, trabajador_id: int, trabajador: string, dni: string, sede: string|null, unidad_organica: string|null, dia: string, motivo: string, suma_descuento: bool, salida: Carbon|null, retorno: Carbon|null, minutos_brutos: int, minutos_refrigerio: int, minutos: int}>  $detalle
     */
    public function __construct(
        protected Collection $resumen,
        protected Collection $detalle,
    ) {}

    public function sheets(): array
    {
        return [
            'Resumen mensual' => new HorasAcumuladasResumenSheet($this->resumen),
            'Detalle diario' => new HorasAcumuladasDetalleSheet($this->detalle),
        ];
    }
}
