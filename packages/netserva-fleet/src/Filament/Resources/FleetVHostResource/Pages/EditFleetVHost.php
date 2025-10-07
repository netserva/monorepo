<?php

namespace NetServa\Fleet\Filament\Resources\FleetVHostResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NetServa\Fleet\Filament\Resources\FleetVHostResource;

class EditFleetVHost extends EditRecord
{
    protected static string $resource = FleetVHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
