<?php

namespace NetServa\Core\Filament\Resources\SetupComponentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\SetupComponentResource;

class ListSetupComponents extends ListRecords
{
    protected static string $resource = SetupComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
