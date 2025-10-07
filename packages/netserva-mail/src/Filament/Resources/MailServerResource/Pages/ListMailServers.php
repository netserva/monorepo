<?php

namespace NetServa\Mail\Filament\Resources\MailServerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Mail\Filament\Resources\MailServerResource;

class ListMailServers extends ListRecords
{
    protected static string $resource = MailServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
