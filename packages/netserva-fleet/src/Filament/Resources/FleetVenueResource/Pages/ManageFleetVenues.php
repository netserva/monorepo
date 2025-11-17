<?php

namespace NetServa\Fleet\Filament\Resources\FleetVenueResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use NetServa\Fleet\Filament\Resources\FleetVenueResource;

class ManageFleetVenues extends ManageRecords
{
    protected static string $resource = FleetVenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
