<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class HorasAcumuladasResumenSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected \Illuminate\Support\Collection $resumen) {}

    public function collection()
    {
        return $this->resumen;
    }

    public function title(): string
    {
        return 'Resumen mensual';
    }

    public function headings(): array
    {
        return [
            'Trabajador',
            'Sede',
            'Unidad orgánica',
            'Papeletas',
            'Horas totales',
            'Horas con descuento',
            'Papeletas con descuento',
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
            $fila['papeletas'],
            $this->formatoHoras($fila['minutos_totales']),
            $this->formatoHoras($fila['minutos_con_descuento']),
            $fila['papeletas_con_descuento'],
        ];
    }

    private function formatoHoras(int $minutos): string
    {
        return sprintf('%dh %02dm', intdiv($minutos, 60), $minutos % 60);
    }
}
