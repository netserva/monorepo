<?php

namespace NetServa\Cron\Filament\Resources\AutomationTaskResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Cron\Filament\Resources\AutomationTaskResource;

class ListAutomationTasks extends ListRecords
{
    protected static string $resource = AutomationTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
