<?php

namespace NetServa\Fleet\Filament\Resources\FleetVenueResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use NetServa\Fleet\Filament\Resources\FleetVenueResource;

class ManageFleetVenues extends ManageRecords
{
    protected static string $resource = FleetVenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Venue')
                ->icon(Heroicon::OutlinedPlus)
                ->createAnother(false),
        ];
    }
}
