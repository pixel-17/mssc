<?php

namespace App\Filament\Resources\MotivoResource\Pages;

use App\Filament\Resources\MotivoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMotivo extends EditRecord
{
    protected static string $resource = MotivoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
