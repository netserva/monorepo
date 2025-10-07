<?php

namespace NetServa\Mail\Filament\Resources\MailboxResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Mail\Filament\Resources\MailboxResource;

class ListMailboxes extends ListRecords
{
    protected static string $resource = MailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
