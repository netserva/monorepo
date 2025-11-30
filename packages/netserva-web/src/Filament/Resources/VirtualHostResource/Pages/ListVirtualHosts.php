<?php

namespace NetServa\Web\Filament\Resources\VirtualHostResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Web\Filament\Resources\VirtualHostResource;

class ListVirtualHosts extends ListRecords
{
    protected static string $resource = VirtualHostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => VirtualHostResource::getFormSchema()),
        ];
    }
}
