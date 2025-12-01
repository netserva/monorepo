<?php

namespace NetServa\Dns\Filament\Resources\DnsZoneResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Dns\Filament\Resources\DnsZoneResource;

class ListDnsZones extends ListRecords
{
    protected static string $resource = DnsZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => DnsZoneResource::getFormSchema()),
        ];
    }
}
