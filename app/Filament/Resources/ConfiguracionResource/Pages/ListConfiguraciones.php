<?php

namespace App\Filament\Resources\ConfiguracionResource\Pages;

use App\Filament\Resources\ConfiguracionResource;
use Filament\Resources\Pages\ListRecords;

class ListConfiguraciones extends ListRecords
{
    protected static string $resource = ConfiguracionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
