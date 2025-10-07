<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsMetricResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\AnalyticsMetricResource;

class EditAnalyticsMetric extends EditRecord
{
    protected static string $resource = AnalyticsMetricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
