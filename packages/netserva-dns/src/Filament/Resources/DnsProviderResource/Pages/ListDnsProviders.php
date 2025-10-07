<?php

namespace NetServa\Dns\Filament\Resources\DnsProviderResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Dns\Filament\Resources\DnsProviderResource;

class ListDnsProviders extends ListRecords
{
    protected static string $resource = DnsProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
