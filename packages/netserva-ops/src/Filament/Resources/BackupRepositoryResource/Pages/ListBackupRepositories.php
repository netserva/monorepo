<?php

namespace NetServa\Ops\Filament\Resources\BackupRepositoryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource;

class ListBackupRepositories extends ListRecords
{
    protected static string $resource = BackupRepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
