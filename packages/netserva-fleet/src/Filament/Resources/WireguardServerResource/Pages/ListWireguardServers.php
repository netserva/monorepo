<?php

namespace NetServa\Fleet\Filament\Resources\WireguardServerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Fleet\Filament\Resources\WireguardServerResource;

class ListWireguardServers extends ListRecords
{
    protected static string $resource = WireguardServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => WireguardServerResource::getFormSchema()),
        ];
    }
}
