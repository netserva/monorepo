<?php

namespace NetServa\Core\Filament\Resources\SystemServiceResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\SystemServiceResource;

class ListSystemServices extends ListRecords
{
    protected static string $resource = SystemServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
