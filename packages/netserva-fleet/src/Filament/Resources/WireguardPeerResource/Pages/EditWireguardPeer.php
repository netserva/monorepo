<?php

namespace NetServa\Fleet\Filament\Resources\WireguardPeerResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Fleet\Filament\Resources\WireguardPeerResource;

class EditWireguardPeer extends EditRecord
{
    protected static string $resource = WireguardPeerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
