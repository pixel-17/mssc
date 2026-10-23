<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja "Detalle diario": una fila por papeleta, para sustentar cada
 * descuento si RR. HH. o el trabajador lo cuestiona. Mismo estilo visual
 * que "Resumen mensual" (encabezado resaltado, columnas autoajustadas,
 * bordes) para que ambas hojas se lean como parte del mismo reporte.
 */
class HorasAcumuladasDetalleSheet implements FromCollection, ShouldAutoSize, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(protected Collection $detalle) {}

    public function collection(): Collection
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
            'DNI',
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
            $fila['dni'],
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

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 14,
            'C' => 20,
            'D' => 26,
            'E' => 14,
            'F' => 22,
            'G' => 14,
            'H' => 18,
            'I' => 18,
            'J' => 12,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $filas = $this->detalle->count();
        $ultimaFila = $filas + 1;

        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);

        if ($filas > 0) {
            $sheet->getStyle("E2:E{$ultimaFila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G2:J{$ultimaFila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("A1:J{$ultimaFila}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->freezePane('A2');
            },
        ];
    }
}
