<?php

namespace NetServa\Fleet\Filament\Resources\FleetVenueResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Fleet\Filament\Resources\FleetVenueResource;

class ViewFleetVenue extends ViewRecord
{
    protected static string $resource = FleetVenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
