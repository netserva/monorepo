<?php

namespace NetServa\Fleet\Filament\Resources\IpAddressResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Fleet\Filament\Resources\IpAddressResource;

class ListIpAddresses extends ListRecords
{
    protected static string $resource = IpAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
