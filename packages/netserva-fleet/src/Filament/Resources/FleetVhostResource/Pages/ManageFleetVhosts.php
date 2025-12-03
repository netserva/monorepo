<?php

namespace NetServa\Fleet\Filament\Resources\FleetVhostResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use NetServa\Fleet\Filament\Resources\FleetVhostResource;

class ManageFleetVhosts extends ManageRecords
{
    protected static string $resource = FleetVhostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New VHost')
                ->icon(Heroicon::OutlinedPlus)
                ->createAnother(false),
        ];
    }
}
