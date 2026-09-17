<?php

namespace App\Exports;

use Illuminate\Support\Collection;
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
class HorasAcumuladasExport implements WithMultipleSheets
{
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
