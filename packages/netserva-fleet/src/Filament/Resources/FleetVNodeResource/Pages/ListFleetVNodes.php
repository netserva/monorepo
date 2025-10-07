<?php

namespace NetServa\Fleet\Filament\Resources\FleetVNodeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NetServa\Fleet\Filament\Resources\FleetVNodeResource;

class ListFleetVNodes extends ListRecords
{
    protected static string $resource = FleetVNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
