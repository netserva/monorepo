<?php

namespace NetServa\Fleet\Filament\Resources\FleetVsiteResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Fleet\Filament\Resources\FleetVsiteResource;

class ViewFleetVsite extends ViewRecord
{
    protected static string $resource = FleetVsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
