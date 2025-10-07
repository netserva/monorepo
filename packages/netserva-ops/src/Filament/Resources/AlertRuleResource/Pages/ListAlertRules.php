<?php

namespace NetServa\Ops\Filament\Resources\AlertRuleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Ops\Filament\Resources\AlertRuleResource;

class ListAlertRules extends ListRecords
{
    protected static string $resource = AlertRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
