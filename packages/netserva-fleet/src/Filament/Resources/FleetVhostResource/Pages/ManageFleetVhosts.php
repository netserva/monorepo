<?php

namespace NetServa\Fleet\Filament\Resources\FleetVhostResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use NetServa\Fleet\Filament\Resources\FleetVhostResource;

class ManageFleetVhosts extends ManageRecords
{
    protected static string $resource = FleetVhostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
