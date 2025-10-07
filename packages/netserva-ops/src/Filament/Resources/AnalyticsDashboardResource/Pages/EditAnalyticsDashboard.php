<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsDashboardResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\AnalyticsDashboardResource;

class EditAnalyticsDashboard extends EditRecord
{
    protected static string $resource = AnalyticsDashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
