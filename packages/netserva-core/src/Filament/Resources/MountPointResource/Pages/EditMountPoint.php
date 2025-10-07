<?php

namespace NetServa\Core\Filament\Resources\MountPointResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\MountPointResource;

class EditMountPoint extends EditRecord
{
    protected static string $resource = MountPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
