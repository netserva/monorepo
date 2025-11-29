<?php

namespace NetServa\Core\Filament\Resources\SetupComponentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\SetupComponentResource;

class EditSetupComponent extends EditRecord
{
    protected static string $resource = SetupComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
