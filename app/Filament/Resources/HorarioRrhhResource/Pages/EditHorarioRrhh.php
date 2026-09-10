<?php

namespace App\Filament\Resources\HorarioRrhhResource\Pages;

use App\Filament\Resources\HorarioRrhhResource;
use Filament\Resources\Pages\EditRecord;

class EditHorarioRrhh extends EditRecord
{
    protected static string $resource = HorarioRrhhResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
