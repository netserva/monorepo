<?php

namespace NetServa\Fleet\Filament\Resources\FleetVnodeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use NetServa\Fleet\Filament\Resources\FleetVnodeResource;

class ManageFleetVnodes extends ManageRecords
{
    protected static string $resource = FleetVnodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
