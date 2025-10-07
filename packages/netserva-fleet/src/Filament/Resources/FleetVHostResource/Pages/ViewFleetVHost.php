<?php

namespace NetServa\Fleet\Filament\Resources\FleetVHostResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Fleet\Filament\Resources\FleetVHostResource;

class ViewFleetVHost extends ViewRecord
{
    protected static string $resource = FleetVHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
