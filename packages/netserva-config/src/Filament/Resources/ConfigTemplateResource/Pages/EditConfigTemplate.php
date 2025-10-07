<?php

namespace NetServa\Config\Filament\Resources\ConfigTemplateResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Config\Filament\Resources\ConfigTemplateResource;

class EditConfigTemplate extends EditRecord
{
    protected static string $resource = ConfigTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
