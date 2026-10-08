<?php

namespace App\Exports;

use App\Support\PapeletaEstadoPresentacion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Historial completo de papeletas de UN trabajador (una fila por papeleta).
 * Recibe las filas ya calculadas por HistorialTrabajadorService::historial()
 * para que el Excel sea lo mismo que se ve en pantalla.
 */
class HistorialTrabajadorExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    /** @param  Collection<int, array<string, mixed>>  $historial */
    public function __construct(protected Collection $historial) {}

    public function collection(): Collection
    {
        return $this->historial;
    }

    public function title(): string
    {
        return 'Historial';
    }

    public function headings(): array
    {
        return ['N.º', 'Día', 'Motivo', 'Sede', 'Estado', 'Salida', 'Retorno', 'Minutos fuera', 'Descuenta', 'Sustento'];
    }

    /** @param  array<string, mixed>  $fila */
    public function map($fila): array
    {
        return [
            $fila['id'],
            $fila['dia']?->format('d/m/Y'),
            $fila['motivo'],
            $fila['sede'],
            (($fila['abandono'] ?? false)
                ? (PapeletaEstadoPresentacion::porAbandono($fila['estado_fqcn']) ?? PapeletaEstadoPresentacion::para($fila['estado_fqcn']))
                : PapeletaEstadoPresentacion::para($fila['estado_fqcn']))[0],
            $fila['salida']?->format('d/m/Y H:i'),
            $fila['retorno']?->format('d/m/Y H:i'),
            $fila['minutos'],
            $fila['suma_descuento'] ? 'Sí' : 'No',
            $fila['sustento_estado'],
        ];
    }
}
