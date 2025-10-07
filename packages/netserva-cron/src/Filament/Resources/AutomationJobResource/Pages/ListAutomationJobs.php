<?php

namespace NetServa\Cron\Filament\Resources\AutomationJobResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Cron\Filament\Resources\AutomationJobResource;

class ListAutomationJobs extends ListRecords
{
    protected static string $resource = AutomationJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
