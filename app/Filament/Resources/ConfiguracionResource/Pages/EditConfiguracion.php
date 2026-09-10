<?php

namespace App\Filament\Resources\ConfiguracionResource\Pages;

use App\Filament\Resources\ConfiguracionResource;
use Filament\Resources\Pages\EditRecord;

class EditConfiguracion extends EditRecord
{
    protected static string $resource = ConfiguracionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
