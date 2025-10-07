<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrarResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource;

class EditDomainRegistrar extends EditRecord
{
    protected static string $resource = DomainRegistrarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
