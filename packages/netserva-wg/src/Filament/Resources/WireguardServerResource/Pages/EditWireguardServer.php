<?php

namespace NetServa\Wg\Filament\Resources\WireguardServerResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Wg\Filament\Resources\WireguardServerResource;

class EditWireguardServer extends EditRecord
{
    protected static string $resource = WireguardServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
