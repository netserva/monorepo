<?php

namespace NetServa\Web\Filament\Resources\WebApplicationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Web\Filament\Resources\WebApplicationResource;

class ListWebApplications extends ListRecords
{
    protected static string $resource = WebApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
