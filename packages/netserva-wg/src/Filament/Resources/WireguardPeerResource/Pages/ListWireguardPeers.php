<?php

namespace NetServa\Wg\Filament\Resources\WireguardPeerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Wg\Filament\Resources\WireguardPeerResource;

class ListWireguardPeers extends ListRecords
{
    protected static string $resource = WireguardPeerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
