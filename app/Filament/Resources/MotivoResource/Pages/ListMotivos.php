<?php

namespace App\Filament\Resources\MotivoResource\Pages;

use App\Filament\Resources\MotivoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMotivos extends ListRecords
{
    protected static string $resource = MotivoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
