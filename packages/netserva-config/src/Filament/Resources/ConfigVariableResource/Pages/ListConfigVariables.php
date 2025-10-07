<?php

namespace NetServa\Config\Filament\Resources\ConfigVariableResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Config\Filament\Resources\ConfigVariableResource;

class ListConfigVariables extends ListRecords
{
    protected static string $resource = ConfigVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
