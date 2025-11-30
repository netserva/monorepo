<?php

namespace NetServa\Web\Filament\Resources\WebServerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Web\Filament\Resources\WebServerResource;

class ListWebServers extends ListRecords
{
    protected static string $resource = WebServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => WebServerResource::getFormSchema()),
        ];
    }
}
