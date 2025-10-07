<?php

namespace NetServa\Config\Filament\Resources\ConfigProfileResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Config\Filament\Resources\ConfigProfileResource;

class ListConfigProfiles extends ListRecords
{
    protected static string $resource = ConfigProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
