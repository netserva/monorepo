<?php

namespace NetServa\Mail\Filament\Resources\MailQueueResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use NetServa\Mail\Filament\Resources\MailQueueResource;

class ListMailQueues extends ListRecords
{
    protected static string $resource = MailQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
