<?php

namespace NetServa\Fleet\Filament\Resources\FleetVSiteResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use NetServa\Fleet\Filament\Resources\FleetVSiteResource;

class ListFleetVSites extends ListRecords
{
    protected static string $resource = FleetVSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
