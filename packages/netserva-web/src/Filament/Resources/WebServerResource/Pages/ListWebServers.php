<?php

namespace NetServa\Web\Filament\Resources\WebServerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Web\Filament\Resources\WebServerResource;

class ListWebServers extends ListRecords
{
    protected static string $resource = WebServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
