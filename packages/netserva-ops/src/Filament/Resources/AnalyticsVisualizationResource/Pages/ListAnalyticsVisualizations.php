<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource;

class ListAnalyticsVisualizations extends ListRecords
{
    protected static string $resource = AnalyticsVisualizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
