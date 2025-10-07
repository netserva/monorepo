<?php

namespace NetServa\Ops\Filament\Resources\BackupSnapshotResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\BackupSnapshotResource;

class EditBackupSnapshot extends EditRecord
{
    protected static string $resource = BackupSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
