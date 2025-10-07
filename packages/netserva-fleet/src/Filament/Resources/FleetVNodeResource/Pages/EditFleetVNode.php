<?php

namespace NetServa\Fleet\Filament\Resources\FleetVNodeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use NetServa\Fleet\Filament\Resources\FleetVNodeResource;

class EditFleetVNode extends EditRecord
{
    protected static string $resource = FleetVNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
