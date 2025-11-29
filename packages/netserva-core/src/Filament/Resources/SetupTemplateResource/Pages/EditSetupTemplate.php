<?php

namespace NetServa\Core\Filament\Resources\SetupTemplateResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Core\Filament\Resources\SetupTemplateResource;

class EditSetupTemplate extends EditRecord
{
    protected static string $resource = SetupTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
