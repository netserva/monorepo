<?php

namespace NetServa\Core\Filament\Resources\MigrationJobResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Core\Filament\Resources\MigrationJobResource;

class ListMigrationJobs extends ListRecords
{
    protected static string $resource = MigrationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
