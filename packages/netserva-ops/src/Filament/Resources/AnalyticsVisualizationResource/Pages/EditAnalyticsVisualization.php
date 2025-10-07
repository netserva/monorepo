<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\AnalyticsVisualizationResource;

class EditAnalyticsVisualization extends EditRecord
{
    protected static string $resource = AnalyticsVisualizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
