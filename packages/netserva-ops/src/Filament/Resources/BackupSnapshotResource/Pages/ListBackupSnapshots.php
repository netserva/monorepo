<?php

namespace NetServa\Ops\Filament\Resources\BackupSnapshotResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource;

class ListBackupSnapshots extends ListRecords
{
    protected static string $resource = BackupSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
