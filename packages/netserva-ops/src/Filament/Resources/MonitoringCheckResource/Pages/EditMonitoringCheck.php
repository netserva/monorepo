<?php

namespace NetServa\Ops\Filament\Resources\MonitoringCheckResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\MonitoringCheckResource;

class EditMonitoringCheck extends EditRecord
{
    protected static string $resource = MonitoringCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
