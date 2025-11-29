<?php

namespace NetServa\Core\Filament\Resources\SetupJobResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\SetupJobResource;

class EditSetupJob extends EditRecord
{
    protected static string $resource = SetupJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
