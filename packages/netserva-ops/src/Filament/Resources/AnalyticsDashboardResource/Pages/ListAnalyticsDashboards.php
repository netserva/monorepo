<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsDashboardResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource;

class ListAnalyticsDashboards extends ListRecords
{
    protected static string $resource = AnalyticsDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
