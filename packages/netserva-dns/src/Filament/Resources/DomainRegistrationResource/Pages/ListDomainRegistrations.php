<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Dns\Filament\Resources\DomainRegistrationResource;

class ListDomainRegistrations extends ListRecords
{
    protected static string $resource = DomainRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
