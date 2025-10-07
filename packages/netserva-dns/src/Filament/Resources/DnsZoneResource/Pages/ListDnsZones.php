<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Dns\Filament\Resources\DnsZoneResource;

class ListDnsZones extends ListRecords
{
    protected static string $resource = DnsZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
