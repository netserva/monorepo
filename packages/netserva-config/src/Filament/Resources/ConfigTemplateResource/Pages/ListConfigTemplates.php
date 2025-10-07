<?php

namespace NetServa\Config\Filament\Resources\ConfigTemplateResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Config\Filament\Resources\ConfigTemplateResource;

class ListConfigTemplates extends ListRecords
{
    protected static string $resource = ConfigTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
