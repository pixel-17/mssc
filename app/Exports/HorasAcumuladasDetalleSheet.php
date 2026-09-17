<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class HorasAcumuladasDetalleSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected \Illuminate\Support\Collection $detalle) {}

    public function collection()
    {
        return $this->detalle;
    }

    public function title(): string
    {
        return 'Detalle diario';
    }

    public function headings(): array
    {
        return [
            'Trabajador',
            'Sede',
            'Unidad orgánica',
            'Día',
            'Motivo',
            '¿Descuenta?',
            'Salida',
            'Retorno',
            'Horas',
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public function map($fila): array
    {
        return [
            $fila['trabajador'],
            $fila['sede'],
            $fila['unidad_organica'],
            $fila['dia'],
            $fila['motivo'],
            $fila['suma_descuento'] ? 'Sí' : 'No',
            $fila['salida']?->format('d/m/Y H:i'),
            $fila['retorno']?->format('d/m/Y H:i'),
            sprintf('%dh %02dm', intdiv($fila['minutos'], 60), $fila['minutos'] % 60),
        ];
    }
}
