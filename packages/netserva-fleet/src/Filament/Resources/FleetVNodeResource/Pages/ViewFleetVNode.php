<?php

namespace NetServa\Fleet\Filament\Resources\FleetVNodeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Fleet\Filament\Resources\FleetVNodeResource;

class ViewFleetVNode extends ViewRecord
{
    protected static string $resource = FleetVNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
