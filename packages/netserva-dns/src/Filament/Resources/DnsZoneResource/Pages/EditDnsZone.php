<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Dns\Filament\Resources\DnsZoneResource;

class EditDnsZone extends EditRecord
{
    protected static string $resource = DnsZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
