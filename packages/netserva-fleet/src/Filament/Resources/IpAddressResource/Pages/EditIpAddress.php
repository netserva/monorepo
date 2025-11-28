<?php

namespace NetServa\Fleet\Filament\Resources\IpAddressResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Fleet\Filament\Resources\IpAddressResource;

class EditIpAddress extends EditRecord
{
    protected static string $resource = IpAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
