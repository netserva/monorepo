<?php

namespace NetServa\Dns\Filament\Resources\DnsRecordResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Dns\Filament\Resources\DnsRecordResource;

class ListDnsRecords extends ListRecords
{
    protected static string $resource = DnsRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
