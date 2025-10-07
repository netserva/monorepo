<?php

namespace NetServa\Dns\Filament\Resources\DnsRecordResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Dns\Filament\Resources\DnsRecordResource;

class EditDnsRecord extends EditRecord
{
    protected static string $resource = DnsRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
