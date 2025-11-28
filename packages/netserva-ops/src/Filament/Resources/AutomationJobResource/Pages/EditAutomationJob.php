<?php

namespace NetServa\Ops\Filament\Resources\AutomationJobResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\AutomationJobResource;

class EditAutomationJob extends EditRecord
{
    protected static string $resource = AutomationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
