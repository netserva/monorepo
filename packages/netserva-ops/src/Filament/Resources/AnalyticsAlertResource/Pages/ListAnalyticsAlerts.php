<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsAlertResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource;

class ListAnalyticsAlerts extends ListRecords
{
    protected static string $resource = AnalyticsAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
