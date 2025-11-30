<?php

namespace NetServa\Fleet\Filament\Resources\IpReservationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use NetServa\Fleet\Filament\Resources\IpReservationResource;

class ListIpReservations extends ListRecords
{
    protected static string $resource = IpReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth(Width::Medium)
                ->modalFooterActionsAlignment(Alignment::End)
                ->schema(fn () => IpReservationResource::getFormSchema()),
        ];
    }
}
