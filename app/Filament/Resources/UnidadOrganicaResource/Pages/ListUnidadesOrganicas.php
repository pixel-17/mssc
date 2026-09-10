<?php

namespace App\Filament\Resources\UnidadOrganicaResource\Pages;

use App\Filament\Resources\UnidadOrganicaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnidadesOrganicas extends ListRecords
{
    protected static string $resource = UnidadOrganicaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
