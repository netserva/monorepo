<?php

namespace NetServa\Mail\Filament\Resources\MailQueueResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use NetServa\Mail\Filament\Resources\MailQueueResource;

class EditMailQueue extends EditRecord
{
    protected static string $resource = MailQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
