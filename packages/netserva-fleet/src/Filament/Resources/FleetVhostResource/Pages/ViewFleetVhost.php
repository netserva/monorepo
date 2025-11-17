<?php

namespace NetServa\Fleet\Filament\Resources\FleetVhostResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Fleet\Filament\Resources\FleetVhostResource;

class ViewFleetVhost extends ViewRecord
{
    protected static string $resource = FleetVhostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
