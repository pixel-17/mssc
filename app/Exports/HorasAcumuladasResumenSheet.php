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
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja "Resumen mensual": una fila por trabajador con sus horas fuera de
 * sede y cuántas de esas horas descuentan en planilla. Trae toda la
 * información que RR. HH./jefe ve filtrada en pantalla (mismo orden de
 * columnas que la tabla de "Horas y descuentos"), con formato legible
 * para que se entienda sin tener que volver a la aplicación: encabezado
 * resaltado, columnas autoajustadas y las horas con descuento en rojo
 * cuando hay alguna, igual que en pantalla.
 */
class HorasAcumuladasResumenSheet implements FromCollection, ShouldAutoSize, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private int $contador = 0;

    public function __construct(protected Collection $resumen) {}

    public function collection(): Collection
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
            '#',
            'Trabajador',
            'DNI',
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
        $this->contador++;

        return [
            $this->contador,
            $fila['trabajador'],
            $fila['dni'],
            $fila['sede'],
            $fila['unidad_organica'],
            $fila['papeletas'],
            $this->formatoHoras($fila['minutos_totales']),
            $this->formatoHoras($fila['minutos_con_descuento']),
            $fila['papeletas_con_descuento'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 30,
            'C' => 14,
            'D' => 20,
            'E' => 26,
            'F' => 12,
            'G' => 16,
            'H' => 18,
            'I' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $filas = $this->resumen->count();
        $ultimaFila = $filas + 1;

        // Encabezado: fondo oscuro, texto blanco y en negrita, igual que
        // el estilo de la tabla en pantalla.
        $sheet->getStyle('A1:I1')->applyFromArray([
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
            // Columnas numéricas alineadas a la derecha, como en pantalla.
            $sheet->getStyle("A2:A{$ultimaFila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F2:I{$ultimaFila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Bordes suaves en toda la tabla para que se lea como tabla,
            // no como texto suelto.
            $sheet->getStyle("A1:I{$ultimaFila}")->applyFromArray([
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
                $sheet = $event->sheet->getDelegate();

                // Fila de encabezado siempre visible al desplazarse.
                $sheet->freezePane('A2');

                // Resalta en rojo "Horas con descuento" (columna H) cuando
                // el trabajador sí tiene horas que descontar, igual que la
                // tabla en pantalla.
                foreach ($this->resumen->values() as $indice => $fila) {
                    if (($fila['minutos_con_descuento'] ?? 0) > 0) {
                        $filaExcel = $indice + 2;
                        $sheet->getStyle("H{$filaExcel}")->getFont()
                            ->setBold(true)
                            ->setColor(new Color('FFDC2626'));
                    }
                }
            },
        ];
    }

    private function formatoHoras(int $minutos): string
    {
        return sprintf('%dh %02dm', intdiv($minutos, 60), $minutos % 60);
    }
}