<?php

namespace NetServa\Web\Filament\Resources\VirtualHostResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Web\Filament\Resources\VirtualHostResource;

class ListVirtualHosts extends ListRecords
{
    protected static string $resource = VirtualHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
