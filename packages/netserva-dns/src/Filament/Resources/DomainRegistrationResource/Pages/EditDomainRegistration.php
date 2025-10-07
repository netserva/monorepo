<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource;

class EditDomainRegistration extends EditRecord
{
    protected static string $resource = DomainRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
