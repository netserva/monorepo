<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsMetricResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource;

class ListAnalyticsMetrics extends ListRecords
{
    protected static string $resource = AnalyticsMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
