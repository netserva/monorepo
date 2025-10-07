<?php

namespace NetServa\Core\Filament\Resources\VHostResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\VHostResource;

class ListVHosts extends ListRecords
{
    protected static string $resource = VHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
