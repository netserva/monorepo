<?php

namespace NetServa\Web\Filament\Resources\WebApplicationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Web\Filament\Resources\WebApplicationResource;

class ListWebApplications extends ListRecords
{
    protected static string $resource = WebApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => WebApplicationResource::getFormSchema()),
        ];
    }
}
