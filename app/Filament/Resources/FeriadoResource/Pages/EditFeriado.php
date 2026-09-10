<?php

namespace App\Filament\Resources\FeriadoResource\Pages;

use App\Filament\Resources\FeriadoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeriado extends EditRecord
{
    protected static string $resource = FeriadoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
