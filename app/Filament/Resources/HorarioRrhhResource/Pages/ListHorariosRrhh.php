<?php

namespace App\Filament\Resources\HorarioRrhhResource\Pages;

use App\Filament\Resources\HorarioRrhhResource;
use Filament\Resources\Pages\ListRecords;

class ListHorariosRrhh extends ListRecords
{
    protected static string $resource = HorarioRrhhResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
