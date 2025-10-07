<?php

namespace NetServa\Dns\Filament\Resources\DomainRegistrarResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Dns\Filament\Resources\DomainRegistrarResource;

class ListDomainRegistrars extends ListRecords
{
    protected static string $resource = DomainRegistrarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
