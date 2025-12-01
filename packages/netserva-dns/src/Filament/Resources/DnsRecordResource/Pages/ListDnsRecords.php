<?php

namespace NetServa\Dns\Filament\Resources\DnsRecordResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Dns\Filament\Resources\DnsRecordResource;

class ListDnsRecords extends ListRecords
{
    protected static string $resource = DnsRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => DnsRecordResource::getFormSchema()),
        ];
    }
}
