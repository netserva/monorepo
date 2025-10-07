<?php

namespace NetServa\Fleet\Filament\Resources\FleetVSiteResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Fleet\Filament\Resources\FleetVSiteResource;

class ViewFleetVSite extends ViewRecord
{
    protected static string $resource = FleetVSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
