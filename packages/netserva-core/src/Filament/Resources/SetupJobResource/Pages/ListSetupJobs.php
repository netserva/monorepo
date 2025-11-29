<?php

namespace NetServa\Core\Filament\Resources\SetupJobResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\SetupJobResource;

class ListSetupJobs extends ListRecords
{
    protected static string $resource = SetupJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
