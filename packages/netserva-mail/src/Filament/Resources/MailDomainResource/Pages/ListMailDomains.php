<?php

namespace NetServa\Mail\Filament\Resources\MailDomainResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Mail\Filament\Resources\MailDomainResource;

class ListMailDomains extends ListRecords
{
    protected static string $resource = MailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
