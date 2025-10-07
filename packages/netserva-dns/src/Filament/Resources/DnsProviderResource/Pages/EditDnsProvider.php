<?php

namespace NetServa\Dns\Filament\Resources\DnsProviderResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Dns\Filament\Resources\DnsProviderResource;

class EditDnsProvider extends EditRecord
{
    protected static string $resource = DnsProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
