<?php

namespace NetServa\Ipam\Filament\Resources\IpNetworkResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ipam\Filament\Resources\IpNetworkResource;

class EditIpNetwork extends EditRecord
{
    protected static string $resource = IpNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
