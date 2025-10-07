<?php

namespace NetServa\Ipam\Filament\Resources\IpReservationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ipam\Filament\Resources\IpReservationResource;

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
