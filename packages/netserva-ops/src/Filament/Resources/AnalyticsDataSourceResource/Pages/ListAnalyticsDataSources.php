<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource;

class ListAnalyticsDataSources extends ListRecords
{
    protected static string $resource = AnalyticsDataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
