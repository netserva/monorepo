<?php

namespace NetServa\Ops\Filament\Resources\MonitoringCheckResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource;

class ListMonitoringChecks extends ListRecords
{
    protected static string $resource = MonitoringCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
