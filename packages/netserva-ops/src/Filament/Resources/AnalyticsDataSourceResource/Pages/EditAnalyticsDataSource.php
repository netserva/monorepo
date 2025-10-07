<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\AnalyticsDataSourceResource;

class EditAnalyticsDataSource extends EditRecord
{
    protected static string $resource = AnalyticsDataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
