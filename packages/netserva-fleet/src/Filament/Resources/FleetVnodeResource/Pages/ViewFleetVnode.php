<?php

namespace NetServa\Fleet\Filament\Resources\FleetVnodeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use NetServa\Fleet\Filament\Resources\FleetVnodeResource;

class ViewFleetVnode extends ViewRecord
{
    protected static string $resource = FleetVnodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
