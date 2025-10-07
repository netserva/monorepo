<?php

namespace NetServa\Fleet\Filament\Resources\FleetVHostResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NetServa\Fleet\Filament\Resources\FleetVHostResource;

class ListFleetVHosts extends ListRecords
{
    protected static string $resource = FleetVHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
