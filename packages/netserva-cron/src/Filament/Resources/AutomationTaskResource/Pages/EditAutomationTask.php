<?php

namespace NetServa\Cron\Filament\Resources\AutomationTaskResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Cron\Filament\Resources\AutomationTaskResource;

class EditAutomationTask extends EditRecord
{
    protected static string $resource = AutomationTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
