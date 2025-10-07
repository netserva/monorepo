<?php

namespace NetServa\Config\Filament\Resources\ConfigVariableResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Config\Filament\Resources\ConfigVariableResource;

class EditConfigVariable extends EditRecord
{
    protected static string $resource = ConfigVariableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
