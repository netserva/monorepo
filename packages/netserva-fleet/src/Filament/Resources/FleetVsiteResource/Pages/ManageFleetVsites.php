<?php

namespace NetServa\Fleet\Filament\Resources\FleetVsiteResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use NetServa\Fleet\Filament\Resources\FleetVsiteResource;

class ManageFleetVsites extends ManageRecords
{
    protected static string $resource = FleetVsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
