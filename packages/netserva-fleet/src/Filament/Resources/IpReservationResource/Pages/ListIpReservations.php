<?php

namespace NetServa\Fleet\Filament\Resources\IpReservationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Fleet\Filament\Resources\IpReservationResource;

class ListIpReservations extends ListRecords
{
    protected static string $resource = IpReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
