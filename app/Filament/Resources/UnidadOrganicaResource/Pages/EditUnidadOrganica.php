<?php

namespace App\Filament\Resources\UnidadOrganicaResource\Pages;

use App\Filament\Resources\UnidadOrganicaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUnidadOrganica extends EditRecord
{
    protected static string $resource = UnidadOrganicaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
