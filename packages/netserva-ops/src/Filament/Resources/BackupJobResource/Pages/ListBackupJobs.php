<?php

namespace NetServa\Ops\Filament\Resources\BackupJobResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\BackupJobResource;

class ListBackupJobs extends ListRecords
{
    protected static string $resource = BackupJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
