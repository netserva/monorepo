<?php

namespace NetServa\Core\Filament\Resources\SetupTemplateResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\SetupTemplateResource;

class ListSetupTemplates extends ListRecords
{
    protected static string $resource = SetupTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
