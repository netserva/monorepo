<?php

namespace NetServa\Ops\Filament\Resources\AnalyticsAlertResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Ops\Filament\Resources\AnalyticsAlertResource;

class EditAnalyticsAlert extends EditRecord
{
    protected static string $resource = AnalyticsAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
