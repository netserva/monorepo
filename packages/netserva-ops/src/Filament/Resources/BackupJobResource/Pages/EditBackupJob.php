<?php

namespace NetServa\Ops\Filament\Resources\BackupJobResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\BackupJobResource;

class EditBackupJob extends EditRecord
{
    protected static string $resource = BackupJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
