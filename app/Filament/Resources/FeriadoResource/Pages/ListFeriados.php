<?php

namespace App\Filament\Resources\FeriadoResource\Pages;

use App\Filament\Resources\FeriadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeriados extends ListRecords
{
    protected static string $resource = FeriadoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
