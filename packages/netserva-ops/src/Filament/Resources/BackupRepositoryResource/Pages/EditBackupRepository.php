<?php

namespace NetServa\Ops\Filament\Resources\BackupRepositoryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\BackupRepositoryResource;

class EditBackupRepository extends EditRecord
{
    protected static string $resource = BackupRepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
