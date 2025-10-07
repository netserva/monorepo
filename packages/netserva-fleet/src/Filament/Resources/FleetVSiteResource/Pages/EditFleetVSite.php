<?php

namespace NetServa\Fleet\Filament\Resources\FleetVSiteResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NetServa\Fleet\Filament\Resources\FleetVSiteResource;

class EditFleetVSite extends EditRecord
{
    protected static string $resource = FleetVSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
