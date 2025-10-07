<?php

namespace NetServa\Ipam\Filament\Resources\IpAddressResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ipam\Filament\Resources\IpAddressResource;

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
