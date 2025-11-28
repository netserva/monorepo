<?php

namespace NetServa\Fleet\Filament\Resources\IpReservationResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Fleet\Filament\Resources\IpReservationResource;

class EditIpReservation extends EditRecord
{
    protected static string $resource = IpReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
